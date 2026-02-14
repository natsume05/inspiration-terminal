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
                    '虚空之心 (完整)'=>'effect-void-heart', '开发者之怒'=>'effect-dev-fury'
                ];
                if(isset($map[$item['name']])) $decor['name_class'] = $map[$item['name']];
            }
            
            // 头像框映射
            if ($type == 'avatar_frame') {
                $map = [
                    '编织者之歌'=>'frame-weaver', '格林剧团之火'=>'frame-grimm',
                    '格林之子'=>'frame-grimm', '黑客帝国'=>'frame-matrix',
                    '丝之歌旋律'=>'frame-silksong', '风向标'=>'frame-silksong',
                    '辐光'=>'frame-radiance', '发光子宫'=>'frame-weaver'
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