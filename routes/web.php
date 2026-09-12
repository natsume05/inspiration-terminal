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

    // --- Home -------------------------------------------------------------
    $router->get('/', static fn (Request $request): Response => $view->render('home', [
        'pageTitle' => '首页',
        'categories' => $posts->categories(),
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

    // --- Health -----------------------------------------------------------
    $router->get('/health', static fn (): Response => Response::json([
        'ok' => true,
        'driver' => $kernel->database()->driver(),
    ]));
};
