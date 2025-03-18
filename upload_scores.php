<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 检查用户是否已登录
if (!isset($_SESSION['username'])) {
    // 如果没有登录，重定向到登录页面
    header("Location: login.php");
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

// 验证管理员权限的函数
function verifyAdminAccess($conn, $username, $password) {
    $sql = "SELECT password FROM users WHERE username = 'admin'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hashed_password = $row['password'];
        
        // 验证密码
        if ($username === 'admin' && password_verify($password, $hashed_password)) {
            return true;
        }
    }
    return false;
}

// 处理上传的CSV文件
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $is_admin = false;
    
    // 如果当前用户不是管理员，则需要验证管理员密码
    if ($_SESSION['username'] !== 'admin') {
        if (isset($_POST['admin_password']) && !empty($_POST['admin_password'])) {
            $is_admin = verifyAdminAccess($conn, 'admin', $_POST['admin_password']);
        }
        
        if (!$is_admin) {
            echo "<div style='color: red; margin-bottom: 20px;'>您需要管理员权限才能上传成绩。请提供管理员密码。</div>";
            // 不执行后续上传操作
            goto skip_upload;
        }
    } else {
        $is_admin = true;
    }
    
    // 以下代码只有管理员权限才会执行
    if ($is_admin) {
        $fileTmpName = $_FILES['file']['tmp_name'];
        $exam_name = $_FILES['file']['name']; // 获取文件名作为考试名称
        $timestamp = time(); // 使用当前时间戳生成表名

        // 创建一个新的表来存储该考试成绩
        $createTableQuery = "CREATE TABLE IF NOT EXISTS scores_$timestamp (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
            chinese FLOAT,
            math FLOAT,
            english FLOAT,
            physics FLOAT,
            chemistry FLOAT,
            biology FLOAT,
            history FLOAT,
            politics FLOAT,
            geology FLOAT,
            exam_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
            UNIQUE KEY (student_id)
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";

        if (!$conn->query($createTableQuery)) {
            die("创建表失败: " . $conn->error);
        }

        // 打开CSV文件
        if (($handle = fopen($fileTmpName, 'r')) !== false) {
            // 读取第一行判断文件编码
            $first_line = fgets($handle);
            rewind($handle); // 重置文件指针到开头
            
            // 检测CSV文件编码并转换
            $encoding = mb_detect_encoding($first_line, "UTF-8, GBK, GB2312", true);
            if ($encoding != "UTF-8") {
                // 如果不是UTF-8，创建一个临时文件来转换编码
                $temp_file = tempnam(sys_get_temp_dir(), 'csv_');
                $temp_handle = fopen($temp_file, 'w');
                
                while (($data = fgets($handle)) !== false) {
                    fputs($temp_handle, mb_convert_encoding($data, "UTF-8", $encoding));
                }
                
                fclose($handle);
                fclose($temp_handle);
                
                $handle = fopen($temp_file, 'r');
            }
            
            // 尝试检测分隔符
            $first_line = fgets($handle);
            rewind($handle);
            
            // 检查是制表符还是逗号分隔
            $delimiter = ","; // 默认使用逗号
            if (strpos($first_line, "\t") !== false) {
                $delimiter = "\t"; // 如果包含制表符，则使用制表符作为分隔符
            }
            
            echo "使用分隔符: " . ($delimiter == "\t" ? "tab" : "comma") . "<br>";
            
            // 读取标题行
            $header = fgetcsv($handle, 1000, $delimiter, '"', '"');
            $count = 0; // 计数成功导入的记录

            // 准备SQL插入数据
            while (($data = fgetcsv($handle, 1000, $delimiter, '"', '"')) !== false) {
                // 打印调试信息
                echo "读取到数据行: " . implode(", ", $data) . "<br>";
                echo "列数: " . count($data) . "<br>";
                
                if (count($data) >= 5) {
                    $student_id = $data[1];   // 学号
                    $name = $data[0];         // 姓名
                    $chinese = $data[2];      // 语文
                    $math = $data[3];         // 数学
                    $english = $data[4];      // 英语
                    $physics = isset($data[5]) ? $data[5] : null;      // 物理
                    $chemistry = isset($data[6]) ? $data[6] : null;    // 化学
                    $biology = isset($data[7]) ? $data[7] : null;      // 生物
                    $history = isset($data[8]) ? $data[8] : null;      // 历史
                    $politics = isset($data[9]) ? $data[9] : null;     // 政治
                    $geology = isset($data[10]) ? $data[10] : null;    // 地理

                    // 插入数据到新创建的表
                    $stmt = $conn->prepare("INSERT INTO scores_$timestamp 
                                   (student_id, name, chinese, math, english, physics, chemistry, biology, history, politics, geology, exam_name) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    // 确保绑定正确的参数
                    $stmt->bind_param("issdddddddds", $student_id, $name, $chinese, $math, $english, $physics, $chemistry, $biology, $history, $politics, $geology, $exam_name);

                    if ($stmt->execute()) {
                        $count++;
                        echo "成功插入学号: $student_id<br>";
                    } else {
                        echo "插入失败 (学号: $student_id): " . $stmt->error . "<br>";
                    }
                    $stmt->close();
                } else {
                    echo "CSV文件格式不正确，行数据不完整: " . implode(", ", $data) . "<br>";
                }
            }

            fclose($handle);
            
            // 删除临时文件（如果存在）
            if (isset($temp_file) && file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            echo "成绩上传成功！共导入 $count 条记录。<br>";
            echo "表名: scores_$timestamp<br>";
        } else {
            echo "上传失败，请选择有效的CSV文件。<br>";
        }
    }
}

skip_upload:

// 更新现有表结构，添加新学科
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tables'])) {
    $is_admin = false;
    
    // 如果当前用户不是管理员，则需要验证管理员密码
    if ($_SESSION['username'] !== 'admin') {
        if (isset($_POST['admin_password_update']) && !empty($_POST['admin_password_update'])) {
            $is_admin = verifyAdminAccess($conn, 'admin', $_POST['admin_password_update']);
        }
        
        if (!$is_admin) {
            echo "<div style='color: red; margin-bottom: 20px;'>您需要管理员权限才能更新表结构。请提供管理员密码。</div>";
            goto skip_update;
        }
    } else {
        $is_admin = true;
    }
    
    if ($is_admin) {
        $tables_result = $conn->query("SHOW TABLES LIKE 'scores_%'");
        $updated_tables = 0;
        
        while ($table_row = $tables_result->fetch_row()) {
            $table = $table_row[0];
            
            // 检查并添加历史列
            if (!$conn->query("SHOW COLUMNS FROM `$table` LIKE 'history'")->num_rows) {
                $conn->query("ALTER TABLE `$table` ADD COLUMN history FLOAT");
                $updated_tables++;
            }
            
            // 检查并添加政治列
            if (!$conn->query("SHOW COLUMNS FROM `$table` LIKE 'politics'")->num_rows) {
                $conn->query("ALTER TABLE `$table` ADD COLUMN politics FLOAT");
                $updated_tables++;
            }
            
            // 检查并添加地理列
            if (!$conn->query("SHOW COLUMNS FROM `$table` LIKE 'geology'")->num_rows) {
                $conn->query("ALTER TABLE `$table` ADD COLUMN geology FLOAT");
                $updated_tables++;
            }
        }
        
        echo "<div style='color: green;'>表结构更新完成！已更新 $updated_tables 个表。</div><br>";
    }
}

skip_update:

// 处理删除整次考试成绩
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_exam_name'])) {
    $is_admin = false;
    
    // 如果当前用户不是管理员，则需要验证管理员密码
    if ($_SESSION['username'] !== 'admin') {
        if (isset($_POST['admin_password_delete']) && !empty($_POST['admin_password_delete'])) {
            $is_admin = verifyAdminAccess($conn, 'admin', $_POST['admin_password_delete']);
        }
        
        if (!$is_admin) {
            echo "<div style='color: red; margin-bottom: 20px;'>您需要管理员权限才能删除考试成绩。请提供管理员密码。</div>";
            goto skip_delete;
        }
    } else {
        $is_admin = true;
    }
    
    if ($is_admin) {
        $exam_name = $_POST['delete_exam_name'];
        
        // 验证表是否存在
        $check_table = $conn->query("SHOW TABLES LIKE 'scores_$exam_name'");
        
        if ($check_table->num_rows > 0) {
            try {
                // 尝试删除对应考试名称的所有成绩（删除对应的表）
                $result = $conn->query("DROP TABLE scores_$exam_name");
                
                if ($result) {
                    echo "<div style='color: green;'>考试ID为 '$exam_name' 的成绩删除成功！</div><br>";
                } else {
                    echo "<div style='color: red;'>删除失败: " . $conn->error . "</div><br>";
                }
            } catch (Exception $e) {
                echo "<div style='color: red;'>删除操作出错: " . $e->getMessage() . "</div><br>";
            }
        } else {
            echo "<div style='color: red;'>未找到考试ID为 '$exam_name' 的表。</div><br>";
        }
    }
}

skip_delete:

// 获取所有考试表
$exam_tables = [];
$tables_result = $conn->query("SHOW TABLES LIKE 'scores_%'");
while ($table_row = $tables_result->fetch_row()) {
    $exam_tables[] = $table_row[0];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>上传与删除成绩</title>
</head>
<body>
    <div class="nav-bar">
        <a href="query_scores.php">查询成绩</a>
        <a href="upload_scores.php">上传成绩</a>
        <!-- 仅为管理员显示的链接 -->
        <?php if (isset($_SESSION['username']) && $_SESSION['username'] === 'admin'): ?>
        <a href="display_scores.php">成绩总览</a>
        <?php endif; ?>
        <a href="logout.php">退出登录</a>
        <div class="user-info">
            当前用户: <?php echo htmlspecialchars($_SESSION['username']); ?>
        </div>
    </div>

    <h1>上传与删除成绩</h1>

    <!-- 上传成绩功能 -->
    <h2>上传成绩（CSV）</h2>
    <form action="upload_scores.php" method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
        <label for="file">选择CSV文件上传：</label>
        <input type="file" name="file" id="file" accept=".csv" required><br><br>
        
        <!-- 非管理员用户需要输入管理员密码 -->
        <?php if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin'): ?>
        <div class="admin-field">
            <label for="admin_password">管理员密码：</label>
            <input type="password" name="admin_password" id="admin_password" required>
        </div>
        <?php endif; ?>
        
        <input type="submit" value="上传">
    </form>
    
    <div class="note">
        <p><strong>注意：</strong>CSV文件支持逗号分隔或制表符分隔的格式，支持UTF-8、GBK或GB2312编码。</p>
        <p>文件格式：姓名、学号、语文成绩、数学成绩、英语成绩、物理成绩、化学成绩、生物成绩、历史成绩、政治成绩、地理成绩</p>
    </div>

    <hr>

    <!-- 更新表结构功能 -->
    <h2>更新现有表结构</h2>
    <p>点击下面的按钮将为所有现有表添加历史、政治和地理科目列（如果尚未存在）</p>
    <form action="upload_scores.php" method="POST">
        <input type="hidden" name="update_tables" value="1">
        
        <!-- 非管理员用户需要输入管理员密码 -->
        <?php if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin'): ?>
        <div class="admin-field">
            <label for="admin_password_update">管理员密码：</label>
            <input type="password" name="admin_password_update" id="admin_password_update" required>
        </div>
        <?php endif; ?>
        
        <input type="submit" value="更新表结构">
    </form>

    <hr>

    <!-- 删除成绩功能 -->
    <h2>删除整次考试成绩</h2>
    <form action="upload_scores.php" method="POST" onsubmit="return confirm('警告：此操作将永久删除所选考试的所有成绩数据，无法恢复。确定要继续吗？');">
        <label for="delete_exam_name">请选择要删除的考试：</label>
        <select name="delete_exam_name" id="delete_exam_name" required>
            <option value="">-- 请选择 --</option>
            <?php foreach ($exam_tables as $table): ?>
                <?php $table_id = str_replace('scores_', '', $table); ?>
                <option value="<?php echo htmlspecialchars($table_id); ?>"><?php echo htmlspecialchars($table); ?></option>
            <?php endforeach; ?>
        </select><br><br>
        
        <!-- 非管理员用户需要输入管理员密码 -->
        <?php if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin'): ?>
        <div class="admin-field">
            <label for="admin_password_delete">管理员密码：</label>
            <input type="password" name="admin_password_delete" id="admin_password_delete" required>
        </div>
        <?php endif; ?>
        
        <input type="submit" value="删除">
    </form>

    <hr>

    <!-- 考试列表 -->
    <h2>已有考试列表</h2>
    <?php if (count($exam_tables) > 0): ?>
        <table>
            <tr>
                <th>考试表名</th>
                <th>创建时间</th>
                <th>记录数</th>
            </tr>
            <?php foreach ($exam_tables as $table): ?>
                <?php 
                    $table_id = str_replace('scores_', '', $table);
                    $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
                    $count_row = $count_result->fetch_assoc();
                    $record_count = $count_row['count'];
                    $date = date('Y-m-d H:i:s', (int)$table_id);
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($table); ?></td>
                    <td><?php echo $date; ?></td>
                    <td><?php echo $record_count; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>暂无考试数据。</p>
    <?php endif; ?>

    <hr>
    <a href="query_scores.php">跳转到查询成绩页面</a>
    <?php if (isset($_SESSION['username']) && $_SESSION['username'] === 'admin'): ?>
    <a href="display_scores.php">查看所有成绩</a>
    <?php endif; ?>
</body>
</html>