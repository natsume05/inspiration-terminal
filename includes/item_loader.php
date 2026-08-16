<?php
/**
 * 用户装扮加载器。
 */

/**
 * 名字特效映射表（装备名 -> CSS 类）。
 */
function decoration_effect_map()
{
    return [
        '苍绿之径苔藓' => 'effect-green-moss',
        '梦之钉' => 'effect-dream-nail',
        '冲刺大师' => 'effect-sprint-master',
        '苍白矿石' => 'effect-pale-ore',
        '主要核心' => 'effect-main-core',
        '辐光' => 'effect-radiance',
        '辐光之辉' => 'effect-radiance',
        '虚空之心' => 'effect-void-heart',
        '虚空之心 (完整)' => 'effect-void-heart',
        '开发者之怒' => 'effect-dev-fury',
        '可怕的领带' => 'effect-sprint-master',
        '争先红葫芦' => 'effect-kim-jacket',
        '异教徒头套' => 'effect-main-core',
        '金的夹克' => 'effect-kim-jacket',
        '量子卫星' => 'effect-quantum',
        '时间循环' => 'effect-quantum',
        '金箍棒' => 'effect-wukong',
        '大圣归来' => 'effect-wukong',
        '心脏跳动' => 'effect-spire-heart',
        '苍白 (Pale)' => 'effect-pale-glitch',
        '五彩碎片' => 'effect-prismatic',
        '神之眼 (风)' => 'effect-anemo',
        '神之眼 (雷)' => 'effect-electro',
        '岩王帝君' => 'effect-geo-lord',
        '狩魔猎人感官' => 'effect-witcher-sense',
        '银剑' => 'effect-silver-sword',
        '奥卓德克' => 'effect-odradek',
        '时间雨' => 'effect-dev-fury',
        '异议！(Objection!)' => 'effect-objection',
        '看招！(Take That!)' => 'effect-objection',
        '天外飞仙' => 'effect-flying-fairy',
        '经脉图' => 'effect-main-core',
        '猛击 (Bash)' => 'effect-sprint-master',
        '千变万化' => 'effect-prismatic',
    ];
}

/**
 * 头像框映射表（装备名 -> CSS 类）。
 */
function decoration_frame_map()
{
    return [
        '编织者之歌' => 'frame-weaver',
        '格林剧团之火' => 'frame-grimm',
        '格林之子' => 'frame-grimm',
        '黑客帝国' => 'frame-matrix',
        '丝之歌旋律' => 'frame-silksong',
        '风向标' => 'frame-silksong',
        '辐光' => 'frame-radiance',
        '发光子宫' => 'frame-weaver',
        '蓝鸡' => 'frame-blue-chicken',
        '思维阁' => 'frame-matrix',
        '挪迈面具' => 'frame-eye-universe',
        '涅奥的祝福' => 'frame-eye-universe',
        '宇宙之眼' => 'frame-eye-universe',
        '天空岛' => 'frame-celestia',
        '派蒙的王冠' => 'frame-radiance',
        '希里雅' => 'frame-matrix',
        '布里吉婴 (BB)' => 'frame-bb-pod',
        '武林盟主' => 'frame-dragon-lord',
        '灵树之光' => 'frame-spirit-tree',
    ];
}

/**
 * 获取用户已装备的装扮样式。
 *
 * @return array{name_class:string, avatar_class:string, badge_icon:string}
 */
function get_user_decorations($conn, $user_id)
{
    $decor = [
        'name_class'   => '',
        'avatar_class' => '',
        'badge_icon'   => '',
    ];

    if (empty($user_id)) {
        return $decor;
    }

    $effect_map = decoration_effect_map();
    $frame_map = decoration_frame_map();

    $stmt = $conn->prepare(
        "SELECT s.type, s.name, s.icon
         FROM user_inventory ui
         JOIN shop_items s ON ui.item_id = s.id
         WHERE ui.user_id = ? AND ui.is_equipped = 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($result && $item = $result->fetch_assoc()) {
        switch ($item['type']) {
            case 'effect':
                if (isset($effect_map[$item['name']])) {
                    $decor['name_class'] = $effect_map[$item['name']];
                }
                break;
            case 'avatar_frame':
                if (isset($frame_map[$item['name']])) {
                    $decor['avatar_class'] = $frame_map[$item['name']];
                }
                break;
            case 'badge':
                $decor['badge_icon'] = $item['icon'];
                break;
        }
    }

    $stmt->close();

    return $decor;
}
