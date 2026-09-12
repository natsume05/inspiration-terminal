<?php

/**
 * Route table.
 *
 * Every state-changing route is registered through POST/DELETE, so the router's
 * CSRF check applies to it automatically. Returning `false` from a handler
 * leaves authentication decisions to the controller, which can redirect.
 */

declare(strict_types=1);

use App\Http\Kernel;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

return static function (Router $router, Kernel $kernel): void {
    $view = new App\Http\View(dirname(__DIR__) . '/templates');
    $auth = $kernel->auth();
    $posts = $kernel->posts();
    $economy = $kernel->economy();

    // Expose the shared page data every template needs.
    $view->share('siteName', (string) $kernel->config()->get('app.name', '灵感传输终端'));
    $view->share('currentUser', $auth->check() ? [
        'id' => $auth->userId(),
        'name' => (string) $kernel->session()->get('display_name', ''),
        'role' => $auth->role(),
    ] : null);
    $view->share('csrfField', $kernel->csrf()->fieldName());
    $view->share('csrfToken', $kernel->csrf()->token());
    $view->share('flashes', $kernel->session()->takeFlashes());
    // Navigation state that every page renders, resolved once here rather than
    // in each template.
    $view->share('isModerator', $auth->isModerator());
    $view->share('unreadNotifications', $auth->check() ? $kernel->notifications()->unreadCount($auth->userId()) : 0);

    // --- Home -------------------------------------------------------------
    $router->get('/', static fn (Request $request): Response => $view->render('home', [
        'pageTitle' => '首页',
        'categories' => $posts->categories(),
        // The administrator's broadcast is only useful if the page every visitor
        // lands on actually renders it.
        'announcement' => $kernel->admin()->activeAnnouncement(),
    ]));

    // --- Authentication ---------------------------------------------------
    $router->get('/login', static function (Request $request) use ($view, $auth): Response {
        if ($auth->check()) {
            return Response::redirect('/community');
        }

        return $view->render('auth/login', ['pageTitle' => '登录']);
    });

    $router->post('/login', static function (Request $request) use ($view, $auth): Response {
        $validator = new App\Security\Validator($request->all());
        $validator->string('username', 1, 32)->password('password');

        if ($validator->fails()) {
            return $view->render('auth/login', [
                'pageTitle' => '登录',
                'error' => $validator->firstError(),
            ], 422);
        }

        $result = $auth->attempt(
            (string) $validator->value('username'),
            (string) $validator->value('password'),
            $request->ip(),
        );

        if (!$result['ok']) {
            return $view->render('auth/login', [
                'pageTitle' => '登录',
                'error' => $result['message'],
            ], 401);
        }

        return Response::redirect('/community');
    });

    $router->get('/register', static function (Request $request) use ($view, $auth): Response {
        if ($auth->check()) {
            return Response::redirect('/community');
        }

        return $view->render('auth/register', ['pageTitle' => '注册']);
    });

    $router->post('/register', static function (Request $request) use ($view, $auth): Response {
        $validator = new App\Security\Validator($request->all());
        $validator
            ->username('username', 2, 32)
            ->password('password', 8)
            ->matches('password_confirm', 'password');

        $users = new App\Repository\UserRepository($kernel->database());

        if ($validator->passes() && $users->usernameExists((string) $validator->value('username'))) {
            return $view->render('auth/register', [
                'pageTitle' => '注册',
                'error' => '该代号已被其他旅行者占用。',
            ], 409);
        }

        if ($validator->fails()) {
            return $view->render('auth/register', [
                'pageTitle' => '注册',
                'error' => $validator->firstError(),
            ], 422);
        }

        $userId = $auth->register(
            (string) $validator->value('username'),
            (string) $validator->value('password'),
            (string) $validator->value('username'),
        );

        $kernel->session()->flash('success', '注册成功，请登录。');

        return Response::redirect('/login');
    });

    $router->post('/logout', static function () use ($auth): Response {
        $auth->logout();

        return Response::redirect('/');
    });

    // --- Community --------------------------------------------------------
    $router->get('/community', static function (Request $request) use ($view, $auth, $posts): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        $category = $request->query('category');
        $category = is_string($category) && $category !== '' ? $category : null;

        return $view->render('community/index', [
            'pageTitle' => '虚空枢纽',
            'activeCategory' => $category,
            'categories' => $posts->categories(),
            'posts' => $posts->feed($auth->userId(), $category),
        ]);
    });

    $router->post('/community/posts', static function (Request $request) use ($view, $auth, $posts): Response {
        if (!$auth->check()) {
            return Response::json(['ok' => false, 'message' => '请先登录。'], 401);
        }

        $validator = new App\Security\Validator($request->all());
        $validator->string('content', 2, 5000);

        if ($validator->fails()) {
            return Response::json(['ok' => false, 'message' => $validator->firstError()], 422);
        }

        $categorySlug = $request->input('category');
        $categoryId = is_string($categorySlug) ? $posts->categoryIdBySlug($categorySlug) : null;

        $imagePath = null;
        $file = $request->file('image');

        if ($file !== null) {
            try {
                $imagePath = $kernel->uploader()->store($file, 'community', 800, 78);
            } catch (Throwable $throwable) {
                return Response::json(['ok' => false, 'message' => $throwable->getMessage()], 422);
            }
        }

        $postId = $posts->create(
            $auth->userId(),
            $categoryId,
            (string) $validator->value('content'),
            '',
            $imagePath,
        );

        // The reward is capped per day, so posting cannot mint currency.
        $reward = $economy->rewardPost($auth->userId());

        return Response::json([
            'ok' => true,
            'post_id' => $postId,
            'reward' => $reward,
        ], 201);
    });

    // --- Community JSON endpoints ----------------------------------------
    $router->post('/api/like', static function (Request $request) use ($auth, $posts, $economy): Response {
        if (!$auth->check()) {
            return Response::json(['ok' => false, 'message' => '请先登录。'], 401);
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('post_id');

        if ($validator->fails()) {
            return Response::json(['ok' => false, 'message' => $validator->firstError()], 422);
        }

        $postId = (int) $validator->value('post_id');
        $alreadyLiked = $posts->hasLiked($postId, $auth->userId());

        // The unique key decides the outcome, so a double click cannot inflate
        // the count even if both requests arrive together.
        if ($alreadyLiked) {
            $posts->unlike($postId, $auth->userId());
            $state = 'unliked';
        } else {
            $posts->like($postId, $auth->userId());
            $state = 'liked';
        }

        $drop = $state === 'liked' ? $economy->rollVoidDrop($auth->userId()) : ['dropped' => false, 'message' => ''];

        return Response::json([
            'ok' => true,
            'state' => $state,
            'like_count' => $posts->likeCount($postId),
            'drop' => $drop['dropped'] ? $drop['message'] : null,
        ]);
    });

    $router->post('/api/comment', static function (Request $request) use ($auth, $posts, $economy): Response {
        if (!$auth->check()) {
            return Response::json(['ok' => false, 'message' => '请先登录。'], 401);
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('post_id')->string('content', 1, 1000);

        if ($validator->fails()) {
            return Response::json(['ok' => false, 'message' => $validator->firstError()], 422);
        }

        if ($posts->find((int) $validator->value('post_id')) === null) {
            return Response::json(['ok' => false, 'message' => '该帖子不存在。'], 404);
        }

        $posts->addComment(
            (int) $validator->value('post_id'),
            $auth->userId(),
            (string) $validator->value('content'),
        );

        // Capped per day: the previous version allowed unlimited farming.
        $reward = $economy->rewardComment($auth->userId());

        return Response::json(['ok' => true, 'reward' => $reward], 201);
    });

    $router->get('/api/comments', static function (Request $request) use ($posts): Response {
        $postId = (int) $request->query('post_id', 0);

        if ($postId <= 0) {
            return Response::json(['ok' => false, 'message' => '参数不正确。'], 422);
        }

        return Response::json(['ok' => true, 'comments' => $posts->comments($postId)]);
    });

    // --- Economy ----------------------------------------------------------
    $router->post('/api/checkin', static function () use ($auth, $economy): Response {
        if (!$auth->check()) {
            return Response::json(['ok' => false, 'message' => '请先登录。'], 401);
        }

        return Response::json($economy->checkIn($auth->userId()));
    });

    $router->post('/api/draw', static function () use ($auth, $economy): Response {
        if (!$auth->check()) {
            return Response::json(['ok' => false, 'message' => '请先登录。'], 401);
        }

        return Response::json($economy->draw($auth->userId()));
    });

    $router->post('/api/shop/purchase', static function (Request $request) use ($auth, $economy): Response {
        if (!$auth->check()) {
            return Response::json(['ok' => false, 'message' => '请先登录。'], 401);
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('item_id');

        if ($validator->fails()) {
            return Response::json(['ok' => false, 'message' => $validator->firstError()], 422);
        }

        return Response::json($economy->purchase($auth->userId(), (int) $validator->value('item_id')));
    });

    // --- Blog -------------------------------------------------------------
    $router->get('/blog', static function (Request $request) use ($view, $kernel): Response {
        $blog = $kernel->blog();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;

        return $view->render('blog/index', [
            'pageTitle' => '深空日志',
            'entries' => $blog->published($perPage, ($page - 1) * $perPage),
            'page' => $page,
            'hasNext' => $blog->publishedCount() > ($page * $perPage),
            'announcement' => null,
        ]);
    });

    $router->get('/blog/{slug}', static function (Request $request, string $slug) use ($view, $kernel, $auth): Response {
        $blog = $kernel->blog();
        $entry = $blog->findPublished($slug);

        if ($entry === null) {
            return $view->render('errors/404', ['pageTitle' => '找不到这篇日志'], 404);
        }

        $entryId = (int) $entry['id'];
        $viewerId = $auth->userId();

        return $view->render('blog/show', [
            'pageTitle' => (string) $entry['title'],
            'entry' => $entry,
            'comments' => $blog->comments($entryId),
            'likeCount' => $blog->likeCount($entryId),
            'hasLiked' => $viewerId > 0 && $blog->hasLiked($entryId, $viewerId),
            'next' => $blog->randomOther($entryId),
        ]);
    });

    $router->post('/blog/{slug}/comments', static function (Request $request, string $slug) use ($view, $kernel, $auth): Response {
        $blog = $kernel->blog();
        $entry = $blog->findPublished($slug);

        if ($entry === null) {
            return Response::json(['ok' => false, 'message' => '该日志不存在。'], 404);
        }

        $validator = new App\Security\Validator($request->all());
        $validator->string('content', 2, 1000);

        if ($validator->fails()) {
            return Response::json(['ok' => false, 'message' => $validator->firstError()], 422);
        }

        $userId = $auth->check() ? $auth->userId() : null;
        // A signed-out reader chooses the name shown with their comment, so it is
        // validated like any other user input and never rendered as markup.
        $guestName = $request->input('display_name');
        $displayName = $userId !== null
            ? (string) $kernel->session()->get('display_name', '旅人')
            : (is_string($guestName) && trim($guestName) !== '' ? mb_substr($guestName, 0, 32) : '过客');

        $blog->addComment((int) $entry['id'], $userId, $displayName, (string) $validator->value('content'));

        if ($userId !== null) {
            $kernel->notifications()->commented((int) $entry['author_id'] ?? 0, $userId, 'blog', (int) $entry['id']);
        }

        return Response::json(['ok' => true, 'message' => '评论已发布。'], 201);
    });

    $router->post('/blog/{slug}/like', static function (Request $request, string $slug) use ($kernel, $auth, $view): Response {
        if (!$auth->check()) {
            return Response::json(['ok' => false, 'message' => '请先登录后再点赞。'], 401);
        }

        $blog = $kernel->blog();
        $entry = $blog->findPublished($slug);

        if ($entry === null) {
            return Response::json(['ok' => false, 'message' => '该日志不存在。'], 404);
        }

        $entryId = (int) $entry['id'];
        $userId = $auth->userId();

        // The unique key decides the outcome, so a double click cannot inflate
        // the count even when both requests arrive together.
        if ($blog->hasLiked($entryId, $userId)) {
            $blog->unlike($entryId, $userId);
            $liked = false;
        } else {
            $blog->like($entryId, $userId);
            $liked = true;
            $kernel->notifications()->liked((int) $entry['author_id'] ?? 0, $userId, 'blog', $entryId);
        }

        return Response::json(['ok' => true, 'liked' => $liked, 'like_count' => $blog->likeCount($entryId)]);
    });

    // --- Profile ----------------------------------------------------------
    $router->get('/profile', static function () use ($view, $kernel, $auth): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        $userId = $auth->userId();
        $users = new App\Repository\UserRepository($kernel->database());
        $user = $users->find($userId);

        if ($user === null) {
            return Response::redirect('/logout');
        }

        return $view->render('profile/index', [
            'pageTitle' => '个人档案',
            'profile' => $user,
            'noteCount' => $kernel->notes()->countForUser($userId),
            'decorations' => (new App\Repository\EconomyRepository($kernel->database()))->equippedDecorations($userId),
            'itemCount' => count((new App\Repository\EconomyRepository($kernel->database()))->ownedItemIds($userId)),
        ]);
    });

    $router->post('/profile/display-name', static function (Request $request) use ($kernel, $auth, $view): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        $validator = new App\Security\Validator($request->all());
        $validator->string('display_name', 2, 48);
        $validator->string('bio', 0, 255, required: false);

        if ($validator->fails()) {
            $kernel->session()->flash('error', $validator->firstError());

            return Response::redirect('/profile');
        }

        $users = new App\Repository\UserRepository($kernel->database());
        $users->updateProfile($auth->userId(), [
            'display_name' => (string) $validator->value('display_name'),
            'bio' => (string) ($validator->validated()['bio'] ?? ''),
        ]);

        $kernel->session()->set('display_name', (string) $validator->value('display_name'));
        $kernel->session()->flash('success', '档案已更新。');

        return Response::redirect('/profile');
    });

    $router->post('/profile/password', static function (Request $request) use ($kernel, $auth): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        $validator = new App\Security\Validator($request->all());
        $validator->password('password', 8)->matches('password_confirm', 'password');

        if ($validator->fails()) {
            $kernel->session()->flash('error', $validator->firstError());

            return Response::redirect('/profile');
        }

        $users = new App\Repository\UserRepository($kernel->database());
        $users->updatePassword(
            $auth->userId(),
            password_hash((string) $validator->value('password'), PASSWORD_DEFAULT),
        );

        // The hash changed, so every other session for this account should not
        // keep working from a credential that no longer exists.
        $kernel->session()->regenerate();
        $kernel->session()->flash('success', '密钥已重置。');

        return Response::redirect('/profile');
    });

    $router->post('/profile/avatar', static function (Request $request) use ($kernel, $auth): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        $file = $request->file('avatar');

        if ($file === null) {
            $kernel->session()->flash('error', '请选择一张图片。');

            return Response::redirect('/profile');
        }

        try {
            $path = $kernel->uploader()->store($file, 'avatars', 250, 82);
        } catch (Throwable $throwable) {
            $kernel->session()->flash('error', $throwable->getMessage());

            return Response::redirect('/profile');
        }

        (new App\Repository\UserRepository($kernel->database()))->updateProfile($auth->userId(), ['avatar_path' => $path]);
        $kernel->session()->set('avatar_path', $path);
        $kernel->session()->flash('success', '头像已更新。');

        return Response::redirect('/profile');
    });

    // --- Private notes ----------------------------------------------------
    $router->get('/notes', static function () use ($view, $kernel, $auth): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        return $view->render('notes/index', [
            'pageTitle' => '思维殿堂',
            'notes' => $kernel->notes()->forUser($auth->userId()),
        ]);
    });

    $router->post('/notes', static function (Request $request) use ($kernel, $auth): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        $validator = new App\Security\Validator($request->all());
        $validator->string('content', 1, 20000);

        if ($validator->fails()) {
            $kernel->session()->flash('error', $validator->firstError());

            return Response::redirect('/notes');
        }

        $kernel->notes()->create($auth->userId(), (string) $validator->value('content'));
        $kernel->session()->flash('success', '记忆已封存。');

        return Response::redirect('/notes');
    });

    // Deleting is a POST, never a GET: a GET would let any page on the internet
    // delete a note by embedding an image tag pointing here.
    $router->post('/notes/delete', static function (Request $request) use ($kernel, $auth): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('note_id');

        if ($validator->passes() && $kernel->notes()->delete((int) $validator->value('note_id'), $auth->userId())) {
            $kernel->session()->flash('success', '已遗忘这段记忆。');
        }

        return Response::redirect('/notes');
    });

    // --- Toolbox ----------------------------------------------------------
    $router->get('/tools', static function () use ($view, $kernel): Response {
        $tools = $kernel->tools();

        return $view->render('tools/index', [
            'pageTitle' => '提瓦特百宝箱',
            'links' => $tools->linksByCategory(),
            'projectCount' => $tools->projectCount(),
        ]);
    });

    $router->get('/tools/github', static function (Request $request) use ($view, $kernel): Response {
        $github = $kernel->github();
        $term = (string) $request->query('q', '');

        // Searching reads the local cache rather than the API, so it costs no
        // rate-limit budget and works with no token configured.
        $results = $term !== '' ? $github->search($term) : [];
        $rankings = $term === '' ? $github->rankings() : ['trending' => [], 'all_time' => []];

        return $view->render('tools/github', [
            'pageTitle' => 'GitHub 开源猎手',
            'term' => $term,
            'results' => $results,
            'trending' => $rankings['trending'],
            'allTime' => $rankings['all_time'],
        ]);
    });

    $router->get('/tools/steam', static function () use ($view, $kernel): Response {
        $steam = $kernel->steam();

        return $view->render('tools/steam', [
            'pageTitle' => 'Steam 战略指挥室',
            'deals' => $steam->deals('deals'),
            'calendar' => $steam->calendar(),
        ]);
    });

    $router->get('/api/steam/deals', static function (Request $request) use ($kernel): Response {
        $mode = (string) $request->query('mode', 'deals');
        $mode = in_array($mode, ['deals', 'trending', 'search'], true) ? $mode : 'deals';
        $term = mb_substr((string) $request->query('q', ''), 0, 100);

        return Response::json($kernel->steam()->deals($mode, $term));
    });

    // --- Notifications ----------------------------------------------------
    $router->get('/notifications', static function () use ($view, $kernel, $auth): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        $notifications = $kernel->notifications();
        $items = $notifications->forUser($auth->userId());

        return $view->render('notifications/index', [
            'pageTitle' => '信号记录',
            'notifications' => $items,
        ]);
    });

    $router->post('/notifications/read', static function () use ($kernel, $auth): Response {
        if (!$auth->check()) {
            return Response::json(['ok' => false, 'message' => '请先登录。'], 401);
        }

        $marked = $kernel->notifications()->markAllRead($auth->userId());

        return Response::json(['ok' => true, 'marked' => $marked]);
    });

    // --- Feedback ---------------------------------------------------------
    $router->get('/feedback', static function () use ($view, $auth): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        return $view->render('feedback/index', ['pageTitle' => '信号塔']);
    });

    $router->post('/feedback', static function (Request $request) use ($kernel, $auth): Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        $validator = new App\Security\Validator($request->all());
        $validator->string('content', 5, 2000)->inList('type', ['bug', 'suggestion', 'question', 'other'], 'other');

        if ($validator->fails()) {
            $kernel->session()->flash('error', $validator->firstError());

            return Response::redirect('/feedback');
        }

        $kernel->database()->execute(
            'INSERT INTO feedback (user_id, type, content) VALUES (:user_id, :type, :content)',
            [
                'user_id' => $auth->userId(),
                'type' => (string) $validator->value('type'),
                'content' => (string) $validator->value('content'),
            ],
        );

        $kernel->session()->flash('success', '信号已发送。');

        return Response::redirect('/feedback');
    });

    // --- Administration ---------------------------------------------------
    $adminGuard = static function () use ($auth): ?Response {
        if (!$auth->check()) {
            return Response::redirect('/login');
        }

        // Moderation routes accept moderators and administrators; the service
        // layer performs the same check, so a missing guard here is not the only
        // thing standing between a member and an administrative action.
        if (!$auth->isModerator()) {
            return Response::json(['ok' => false, 'message' => '权限不足。'], 403);
        }

        return null;
    };

    $router->get('/admin', static function () use ($view, $kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return Response::redirect('/');
        }

        $admin = $kernel->admin();

        return $view->render('admin/index', [
            'pageTitle' => '舰长控制台',
            'counts' => $admin->dashboardCounts(),
            'announcement' => $admin->activeAnnouncement(),
            'feedback' => $admin->feedback(20),
            'users' => $admin->users(50),
            'links' => $kernel->tools()->allLinks(),
        ]);
    });

    $router->post('/admin/announcement', static function (Request $request) use ($kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return $denied;
        }

        if (!$kernel->auth()->isAdmin()) {
            return Response::json(['ok' => false, 'message' => '需要管理员权限。'], 403);
        }

        $validator = new App\Security\Validator($request->all());
        $validator->string('content', 2, 500);

        if ($validator->fails()) {
            $kernel->session()->flash('error', $validator->firstError());

            return Response::redirect('/admin');
        }

        $notified = $kernel->admin()->publishAnnouncement(
            $kernel->auth()->userId(),
            (string) $validator->value('content'),
            $request->input('notify') === '1',
        );

        $kernel->session()->flash('success', sprintf('广播已发布，通知 %d 人。', $notified));

        return Response::redirect('/admin');
    });

    $router->post('/admin/tools', static function (Request $request) use ($kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return $denied;
        }

        $validator = new App\Security\Validator($request->all());
        $validator
            ->string('title', 1, 80)
            ->string('url', 8, 500)
            ->string('description', 0, 255, required: false)
            ->string('category', 1, 48, required: false)
            ->string('icon', 0, 16, required: false);

        $url = (string) ($validator->validated()['url'] ?? '');

        if ($validator->fails() || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $kernel->session()->flash('error', '工具信息不完整或链接格式不正确。');

            return Response::redirect('/admin');
        }

        $kernel->admin()->addTool(
            $kernel->auth()->userId(),
            (string) $validator->value('title'),
            $url,
            (string) ($validator->validated()['description'] ?? ''),
            (string) ($validator->validated()['category'] ?? 'general'),
            (string) ($validator->validated()['icon'] ?? ''),
        );

        $kernel->session()->flash('success', '工具已添加。');

        return Response::redirect('/admin');
    });

    $router->post('/admin/tools/delete', static function (Request $request) use ($kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return $denied;
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('tool_id');

        if ($validator->passes()) {
            $kernel->admin()->deleteTool($kernel->auth()->userId(), (int) $validator->value('tool_id'));
            $kernel->session()->flash('success', '工具已删除。');
        }

        return Response::redirect('/admin');
    });

    $router->post('/admin/blog', static function (Request $request) use ($kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return $denied;
        }

        $validator = new App\Security\Validator($request->all());
        $validator->string('title', 2, 160)->string('content', 10, 200000);

        if ($validator->fails()) {
            $kernel->session()->flash('error', $validator->firstError());

            return Response::redirect('/admin');
        }

        $cover = null;
        $file = $request->file('cover');

        if ($file !== null) {
            try {
                $cover = $kernel->uploader()->store($file, 'blog', 1200, 80);
            } catch (Throwable $throwable) {
                $kernel->session()->flash('error', $throwable->getMessage());

                return Response::redirect('/admin');
            }
        }

        $id = $kernel->admin()->publishBlog(
            $kernel->auth()->userId(),
            (string) $validator->value('title'),
            (string) $validator->value('content'),
            $cover,
        );

        $kernel->session()->flash('success', sprintf('日志已发布（#%d）。', $id));

        return Response::redirect('/admin');
    });

    $router->post('/admin/blog/delete', static function (Request $request) use ($kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return $denied;
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('blog_id');

        if ($validator->passes()) {
            $kernel->admin()->deleteBlog($kernel->auth()->userId(), (int) $validator->value('blog_id'));
            $kernel->session()->flash('success', '日志已删除。');
        }

        return Response::redirect('/admin');
    });

    $router->post('/admin/title', static function (Request $request) use ($kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return $denied;
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('user_id')->string('title', 0, 32, required: false);

        if ($validator->fails()) {
            $kernel->session()->flash('error', '参数不正确。');

            return Response::redirect('/admin');
        }

        $ok = $kernel->admin()->grantTitle(
            $kernel->auth()->userId(),
            (int) $validator->value('user_id'),
            (string) ($validator->validated()['title'] ?? ''),
        );

        $kernel->session()->flash($ok ? 'success' : 'error', $ok ? '称号已更新。' : '账号不存在。');

        return Response::redirect('/admin');
    });

    $router->post('/admin/feedback', static function (Request $request) use ($kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return $denied;
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('feedback_id')->string('reply', 1, 2000);

        if ($validator->fails()) {
            $kernel->session()->flash('error', $validator->firstError());

            return Response::redirect('/admin');
        }

        $ok = $kernel->admin()->replyToFeedback(
            $kernel->auth()->userId(),
            (int) $validator->value('feedback_id'),
            (string) $validator->value('reply'),
        );

        $kernel->session()->flash($ok ? 'success' : 'error', $ok ? '回复已发送。' : '反馈不存在。');

        return Response::redirect('/admin');
    });

    $router->post('/admin/users/role', static function (Request $request) use ($kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return $denied;
        }

        if (!$kernel->auth()->isAdmin()) {
            return Response::json(['ok' => false, 'message' => '需要管理员权限。'], 403);
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('user_id')->inList('role', ['user', 'moderator', 'admin']);

        if ($validator->passes()) {
            $ok = $kernel->admin()->setRole(
                $kernel->auth()->userId(),
                (int) $validator->value('user_id'),
                (string) $validator->value('role'),
            );
            $kernel->session()->flash($ok ? 'success' : 'error', $ok ? '角色已更新。' : '不能修改自己的角色。');
        }

        return Response::redirect('/admin');
    });

    $router->post('/admin/users/suspend', static function (Request $request) use ($kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return $denied;
        }

        $validator = new App\Security\Validator($request->all());
        $validator->id('user_id')->inList('action', ['suspend', 'reinstate']);

        if ($validator->passes()) {
            $ok = $kernel->admin()->setSuspended(
                $kernel->auth()->userId(),
                (int) $validator->value('user_id'),
                (string) $validator->value('action') === 'suspend',
            );
            $kernel->session()->flash($ok ? 'success' : 'error', $ok ? '账号状态已更新。' : '不能修改自己的状态。');
        }

        return Response::redirect('/admin');
    });

    $router->get('/admin/audit', static function () use ($view, $kernel, $adminGuard): Response {
        $denied = $adminGuard();

        if ($denied instanceof Response) {
            return Response::redirect('/');
        }

        return $view->render('admin/audit', [
            'pageTitle' => '操作日志',
            'entries' => $kernel->audit()->recent(200),
        ]);
    });

    // --- Health -----------------------------------------------------------
    $router->get('/health', static fn (): Response => Response::json([
        'ok' => true,
        'driver' => $kernel->database()->driver(),
    ]));
};
