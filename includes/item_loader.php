<?php
// includes/item_loader.php - 装备加载器 (修复版)

function get_user_decorations($conn, $user_id) {
    // 1. 初始化默认空样式
    $decor = [
        'name_class' => '',
        'avatar_class' => '',
        'badge_icon' => ''
    ];

    // 🛑 2. 安全阀：如果用户ID为空 (比如幽灵帖子)，直接返回默认值，防止 SQL 报错
    if (empty($user_id)) {
        return $decor; 
    }

    // 3. 正常查询
    $sql = "
        SELECT s.type, s.name, s.icon
        FROM user_inventory ui
        JOIN shop_items s ON ui.item_id = s.id
        WHERE ui.user_id = $user_id AND ui.is_equipped = 1
    ";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($item = $result->fetch_assoc()) {
            $type = $item['type'];
            
            // 名字特效映射
            if ($type == 'effect') {
                $map = [
                    '苍绿之径苔藓'=>'effect-green-moss', '梦之钉'=>'effect-dream-nail',
                    '冲刺大师'=>'effect-sprint-master', '苍白矿石'=>'effect-pale-ore',
                    '主要核心'=>'effect-main-core', '辐光'=>'effect-radiance',
                    '辐光之辉'=>'effect-radiance', '虚空之心'=>'effect-void-heart',
                    '虚空之心 (完整)'=>'effect-void-heart', '开发者之怒'=>'effect-dev-fury',
                    // 🆕 新增
                    '可怕的领带'=>'effect-sprint-master', // 复用倾斜效果
                    '争先红葫芦'=>'effect-kim-jacket',    // 复用颜色
                    '异教徒头套'=>'effect-main-core',     // 复用绿色或定义新的
                    '金的夹克'=>'effect-kim-jacket',
                    '量子卫星'=>'effect-quantum',
                    '时间循环'=>'effect-quantum',
                    '金箍棒'=>'effect-wukong',
                    '大圣归来'=>'effect-wukong',
                    '心脏跳动'=>'effect-spire-heart',
                    '苍白 (Pale)'=>'effect-pale-glitch',
                    '五彩碎片'=>'effect-prismatic',
                    // 原神
                    '神之眼 (风)' => 'effect-anemo',
                    '神之眼 (雷)' => 'effect-electro',
                    '岩王帝君' => 'effect-geo-lord',
                    // 巫师
                    '狩魔猎人感官' => 'effect-witcher-sense',
                    '银剑' => 'effect-silver-sword',
                    // 死亡搁浅
                    '奥卓德克' => 'effect-odradek',
                    '时间雨' => 'effect-dev-fury', // 复用已有的黑色/红色混乱效果
                    // 逆转裁判
                    '异议！(Objection!)' => 'effect-objection',
                    '看招！(Take That!)' => 'effect-objection', // 复用震动
                    // 武侠
                    '天外飞仙' => 'effect-flying-fairy',
                    '经脉图' => 'effect-main-core', // 复用绿色线条
                    // 奥日
                    '猛击 (Bash)' => 'effect-sprint-master', // 复用冲刺
                    // 传说
                    '千变万化' => 'effect-prismatic'
                ];
                if(isset($map[$item['name']])) $decor['name_class'] = $map[$item['name']];
            }
            
            // 头像框映射
            if ($type == 'avatar_frame') {
                $map = [
                    '编织者之歌'=>'frame-weaver', '格林剧团之火'=>'frame-grimm',
                    '格林之子'=>'frame-grimm', '黑客帝国'=>'frame-matrix',
                    '丝之歌旋律'=>'frame-silksong', '风向标'=>'frame-silksong',
                    '辐光'=>'frame-radiance', '发光子宫'=>'frame-weaver',
                    // 🆕 新增
                    '蓝鸡'=>'frame-blue-chicken',
                    '思维阁'=>'frame-matrix', // 复用科技感
                    '挪迈面具'=>'frame-eye-universe',
                    '涅奥的祝福'=>'frame-eye-universe',
                    '宇宙之眼'=>'frame-eye-universe',
                    // 原神
                    '天空岛' => 'frame-celestia',
                    '派蒙的王冠' => 'frame-radiance', // 复用光辉
                    // 巫师
                    '希里雅' => 'frame-matrix', // 复用绿色传送感
                    // 死亡搁浅
                    '布里吉婴 (BB)' => 'frame-bb-pod',
                    // 武侠
                    '武林盟主' => 'frame-dragon-lord',
                    // 奥日
                    '灵树之光' => 'frame-spirit-tree'
                ];
                if(isset($map[$item['name']])) $decor['avatar_class'] = $map[$item['name']];
            }

            // 徽章映射
            if ($type == 'badge') {
                $decor['badge_icon'] = $item['icon'];
            }
        }
    }
    
    return $decor;
}
?>