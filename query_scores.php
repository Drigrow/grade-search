<?php
session_start(); // Start the session at the beginning of the script

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    // User is not logged in, redirect to login page
    header("Location: login.php");
    exit();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// 查询成绩
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];

    // 获取所有成绩表
    $result = $conn->query("SHOW TABLES LIKE 'scores_%'");
    
    // 存储所有考试的数据
    $exams_data = [];
    $student_name = "";
    $found = false;

    while ($row = $result->fetch_row()) {
        $exam_table = $row[0]; // 获取表名
        
        // 查询该表中的成绩
        $sql = "SELECT * FROM $exam_table WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result_data = $stmt->get_result();
        
        if ($result_data->num_rows > 0) {
            $row_data = $result_data->fetch_assoc();
            $found = true;
            
            // 如果还没有获取到学生姓名，则记录
            if (empty($student_name)) {
                $student_name = $row_data['name'];
            }
            
            // 获取考试名称
            $exam_name = isset($row_data['exam_name']) ? $row_data['exam_name'] : basename($exam_table);
            
            // 格式化日期（如果考试名称是时间戳）
            if (is_numeric($exam_name) || preg_match('/^scores_\d+$/', $exam_table)) {
                $timestamp = is_numeric($exam_name) ? $exam_name : str_replace('scores_', '', $exam_table);
                $formatted_date = date('Y-m-d H:i', (int)$timestamp);
                $exam_name = $formatted_date;
            }
            
            // 存储该考试的成绩
            $exams_data[$exam_table] = [
                'exam_name' => $exam_name,
                'scores' => $row_data
            ];
        }
        
        $stmt->close();
    }

    // 展示查询结果
    if ($found) {
        echo "<h2>学号: $student_id - $student_name</h2>";
        
        // 创建表格
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        
        // 表头 - 考试名称
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>科目</th>";
        foreach ($exams_data as $exam) {
            echo "<th>" . htmlspecialchars($exam['exam_name']) . "</th>";
        }
        echo "</tr>";
        
        // 表格内容 - 按科目展示成绩
        foreach ($subjects as $subject_key => $subject_name) {
            echo "<tr>";
            echo "<td style='font-weight: bold;'>" . $subject_name . "</td>";
            
            foreach ($exams_data as $exam) {
                $score = isset($exam['scores'][$subject_key]) ? $exam['scores'][$subject_key] : "-";
                $style = "";
                echo "<td$style>" . $score . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='color:red;font-weight:bold;margin:20px 0;'>未找到该学号的成绩。</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>查询成绩</title>
    
</head>
<body>
    <h1>查询成绩</h1>
    <div class="query-form">
        <form action="query_scores.php" method="GET" accept-charset="UTF-8">
            <label for="student_id">请输入学号查询成绩：</label>
            <input type="text" name="student_id" id="student_id" required>
            <input type="submit" value="查询">
        </form>
    </div>

    <hr>
    <div class="nav-links">
        <?php if ($_SESSION['username'] === 'admin'): ?>
        <a href="upload_scores.php" class="nav-link">上传成绩页面</a> |
        <a href="display_scores.php" class="nav-link">管理员页面</a> |
        <?php endif; ?>
        <a href="logout.php" class="nav-link">退出登录</a>
    </div>
    <div style="margin-top: 10px;">
        当前用户: <?php echo htmlspecialchars($_SESSION['username']); ?>
    </div>
</body>
</html>