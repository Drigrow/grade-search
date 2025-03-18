<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// 连接到数据库
$servername = "1Panel-mysql-5674";
$username = "grades_user";
$password = "YmnijiWK7XEtb6BK";
$dbname = "grades";

$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 获取表单提交的数据
$user = $_POST['username'];
$pass = $_POST['password'];

// 查询用户信息
$sql = "SELECT password FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($hashed_password);
    $stmt->fetch();

    // 验证密码
    if (password_verify($pass, $hashed_password)) {
        // 登录成功
        // 开始会话以便保存用户信息
        session_start();
        $_SESSION['username'] = $user;
        
        // 如果是管理员，跳转到管理员页面
        if ($user === 'admin') {
            header("Location: display_scores.php");
        } else {
            // 如果是普通用户，跳转到成绩查询页面
            header("Location: query_scores.php");
        }
        exit();
    } else {
        echo "密码错误，请重试。";
    }
} else {
    echo "用户名不存在。";
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>用户登录</title>
</head>
<body>
    <h2>用户登录</h2>
    <form action="login.php" method="post">
        <label for="username">用户名：</label>
        <input type="text" id="username" name="username" required><br><br>
        <label for="password">密码：</label>
        <input type="password" id="password" name="password" required><br><br>
        <input type="submit" value="登录">
    <br>
    <a href="register.php">注册</a>
    </form>
</body>
</html>
