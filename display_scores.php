<?php
session_start();

// 检查用户是否为管理员
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    // 如果不是管理员，显示错误信息和返回登录页面的链接
    echo '<!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <title>权限错误</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
            .error-message { color: red; font-weight: bold; margin-bottom: 20px; }
            .login-link { padding: 10px; background-color: #f0f0f0; text-decoration: none; color: #333; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="error-message">需要管理员账号登录</div>
        <a href="login.php" class="login-link">返回登录页面</a>
    </body>
    </html>';
    exit();
}

// 连接数据库
$servername = "1Panel-mysql-5674";
$username = "grades_user";
$password = "YmnijiWK7XEtb6BK";
$dbname = "grades";

$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 设置字符集为UTF-8
$conn->set_charset("utf8mb4");

// 定义科目列表
$subjects = ['chinese' => '语文', 'math' => '数学', 'english' => '英语', 
             'physics' => '物理', 'chemistry' => '化学', 'biology' => '生物',
             'history' => '历史', 'politics' => '政治', 'geology' => '地理'];

// 获取所有成绩表
$tables_result = $conn->query("SHOW TABLES LIKE 'scores_%'");
$exam_tables = [];

while ($table_row = $tables_result->fetch_row()) {
    $exam_tables[] = $table_row[0];
}

// 获取所有学生ID和姓名
$students = [];
$students_result = $conn->query("SELECT DISTINCT student_id, name FROM " . $exam_tables[0]);

if ($students_result && $students_result->num_rows > 0) {
    while ($student = $students_result->fetch_assoc()) {
        $students[$student['student_id']] = $student['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>成绩管理系统 - 管理员视图</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; position: sticky; top: 0; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .exam-header { margin-top: 40px; background-color: #e7f3fe; padding: 10px; border-left: 6px solid #2196F3; }
        .nav-bar { background-color: #333; overflow: hidden; margin-bottom: 20px; }
        .nav-bar a { float: left; color: white; text-align: center; padding: 14px 16px; text-decoration: none; }
        .nav-bar a:hover { background-color: #ddd; color: black; }
        .logout { float: right; }
    </style>
</head>
<body>
    <div class="nav-bar">
        <a href="#">成绩总览</a>
        <a href="upload_scores.php">上传成绩</a>
        <a href="logout.php" class="logout">退出登录</a>
    </div>

    <h1>成绩管理系统 - 管理员视图</h1>
    
    <?php foreach ($exam_tables as $exam_table): ?>
        <?php
        // 获取考试名称
        $exam_query = $conn->query("SELECT DISTINCT exam_name FROM $exam_table LIMIT 1");
        $exam_name = "";
        
        if ($exam_query && $exam_query->num_rows > 0) {
            $exam_row = $exam_query->fetch_assoc();
            $exam_name = isset($exam_row['exam_name']) ? $exam_row['exam_name'] : basename($exam_table);
        } else {
            $exam_name = basename($exam_table);
        }
        
        // 格式化日期（如果考试名称是时间戳）
        if (is_numeric($exam_name) || preg_match('/^scores_\d+$/', $exam_table)) {
            $timestamp = is_numeric($exam_name) ? $exam_name : str_replace('scores_', '', $exam_table);
            $formatted_date = date('Y-m-d H:i', (int)$timestamp);
            $exam_name = $formatted_date;
        }
        ?>
        
        <h2 class="exam-header">考试: <?php echo htmlspecialchars($exam_name); ?></h2>
        
        <table>
            <tr>
                <th>学号</th>
                <th>姓名</th>
                <?php foreach ($subjects as $subject_name): ?>
                    <th><?php echo $subject_name; ?></th>
                <?php endforeach; ?>
            </tr>
            
            <?php
            $students_scores = $conn->query("SELECT * FROM $exam_table ORDER BY student_id");
            if ($students_scores && $students_scores->num_rows > 0):
                while ($student_row = $students_scores->fetch_assoc()):
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($student_row['student_id']); ?></td>
                    <td><?php echo htmlspecialchars($student_row['name']); ?></td>
                    <?php foreach (array_keys($subjects) as $subject_key): ?>
                        <td><?php echo isset($student_row[$subject_key]) ? htmlspecialchars($student_row[$subject_key]) : "-"; ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php
                endwhile;
            else:
            ?>
                <tr><td colspan="<?php echo count($subjects) + 2; ?>">该考试没有成绩数据。</td></tr>
            <?php endif; ?>
        </table>
    <?php endforeach; ?>

    <?php if (empty($exam_tables)): ?>
        <div style="text-align: center; margin-top: 50px; color: #666;">
            <p>目前没有任何考试数据。请使用上传功能添加考试成绩。</p>
        </div>
    <?php endif; ?>

</body>
</html>

<?php
// 关闭数据库连接
$conn->close();
?>