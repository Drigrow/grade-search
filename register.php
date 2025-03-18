<?php
// 定义正确的邀请码
ini_set('display_errors', 1);
error_reporting(E_ALL);
define('VALID_INVITE_CODE', 'gradesearch');

// 获取表单提交的数据
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$invite_code = $_POST['invite_code'] ?? '';

// 验证邀请码
if ($invite_code !== VALID_INVITE_CODE) {
    die('邀请码错误！');
}

// 连接数据库
$servername = "localhost";
$db_username = "grades_user";
$db_password = "YmnijiWK7XEtb6BK";
$db_name = "grades";

$conn = new mysqli($servername, $db_username, $db_password, $db_name);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 创建 users 表（如果尚未创建）
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)";
if (!$conn->query($sql)) {
    die("创建表失败: " . $conn->error);
}

// 插入新用户数据
$stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $username, password_hash($password, PASSWORD_DEFAULT));

if ($stmt->execute()) {
    echo "注册成功！";
} else {
    echo "注册失败: " . $stmt->error;
}

// 关闭连接
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>用户注册</title>
</head>
<body>
    <h2>用户注册</h2>
    <form action="register.php" method="post">
        <label for="username">用户名：</label>
        <input type="text" id="username" name="username" required><br><br>
        <label for="password">密码：</label>
        <input type="password" id="password" name="password" required><br><br>
        <label for="invite_code">邀请码：</label>
        <input type="text" id="invite_code" name="invite_code" required><br><br>
        <input type="submit" value="注册">
        <br>
        <a href="login.php">登录</a>
    </form>
</body>
</html>
