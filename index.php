<?php
// 下达命令：我是主页，不用导航栏
$page_title = "灵感传输终端";
$style = "index"; 
$show_nav = false; 

include 'includes/header.php'; 
?>

<div class="logo-area">
    <h1>
        <span class="g-blue">In</span><span class="g-red">spi</span><span class="g-yellow">ra</span><span class="g-blue">ti</span><span class="g-green">on</span>
        <span class="g-red">T</span>erminal
    </h1>
    <p class="subtitle">
        这里是灵感的传输终端。连接宇宙深处的信号，聚合实用主义的工具，或是记录虚空中的低语。
    </p>
</div>

<div class="card-container">
    <a href="blog.php" class="card card-blog">
        <span class="icon"> 🚀 </span>
        <h2>深空日志</h2>
        <p>Admin的私人观测站。星际拓荒风格，记录思维的波形与宇宙的余晖。</p>
    </a>

    <a href="tools.php" class="card card-tools">
        <span class="icon"> 🧩 </span>
        <h2>提瓦特百宝箱</h2>
        <p>实用工具聚合。原神UI风格，分区收录Motrix、Everything等冒险家必备道具。</p>
    </a>

    <a href="community.php" class="card card-community">
        <span class="icon"> 🦋 </span>
        <h2>虚空梦语</h2>
        <p>用户交流与灵感记录。空洞骑士风格，在圣巢的石碑上刻下你的记忆（需登录）。</p>
    </a>
</div>

<?php include 'includes/footer.php'; ?>