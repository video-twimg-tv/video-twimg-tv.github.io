<?php
// admin.php - simple password-protected admin UI to view donations
session_start();
require_once __DIR__ . '/config.php';

$logged_in = isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;

// login processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if ($user === $admin_user && password_verify($pass, $admin_password_hash)) {
        $_SESSION['admin_logged'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}

// logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// fetch donations if logged in
$donations = [];
$total = 0;
$perPage = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($logged_in) {
    try {
        if ($db_type === 'sqlite') {
            $pdo = new PDO('sqlite:' . $sqlite_path);
        } else {
            $dsn = "mysql:host={$mysql['host']};port={$mysql['port']};dbname={$mysql['dbname']};charset={$mysql['charset']}";
            $pdo = new PDO($dsn, $mysql['user'], $mysql['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // build query
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE name LIKE :q OR email LIKE :q OR address LIKE :q OR network LIKE :q";
            $params[':q'] = "%{$search}%";
        }
        // count total
        $countSql = "SELECT COUNT(*) FROM donations {$where}";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT * FROM donations {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "DB error: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Donations Admin — Video Twimg Tv</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#f5f7fb;color:#0f172a;padding:20px}
.container{max-width:1100px;margin:0 auto}
.header{display:flex;justify-content:space-between;align-items:center}
.table{width:100%;border-collapse:collapse;margin-top:14px}
.table th, .table td{border:1px solid #e6e9ef;padding:8px;text-align:left;font-size:13px}
.table th{background:#fff}
.search{margin-top:12px}
.login-box{max-width:380px;margin:80px auto;padding:20px;background:#fff;border-radius:8px;box-shadow:0 6px 18px rgba(15,23,42,0.06)}
.notice{color:#e11d48}
.pager{margin-top:12px}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>Donations Admin — Video Twimg Tv</h1>
    <?php if ($logged_in): ?>
      <div><a href="admin.php?action=logout">Logout</a></div>
    <?php endif; ?>
  </div>

<?php if (!$logged_in): ?>
  <div class="login-box">
    <h3>Admin Login</h3>
    <?php if (!empty($error)): ?><div class="notice"><?=htmlspecialchars($error)?></div><?php endif; ?>
    <form method="post" action="admin.php">
      <input type="hidden" name="action" value="login" />
      <div><label>Username<br><input type="text" name="username" required style="width:100%;padding:8px;margin-top:6px"></label></div>
      <div style="margin-top:8px"><label>Password<br><input type="password" name="password" required style="width:100%;padding:8px;margin-top:6px"></label></div>
      <div style="margin-top:12px"><button type="submit" style="padding:10px 14px">Login</button></div>
    </form>
  </div>
<?php else: ?>
  <div>
    <form method="get" action="admin.php" class="search">
      <input type="text" name="q" placeholder="Search name, email, address, network" value="<?=htmlspecialchars($search)?>" style="width:60%;padding:8px" />
      <button type="submit" style="padding:8px 12px">Search</button>
    </form>

    <p>Total donations: <strong><?=htmlspecialchars($total)?></strong></p>

    <table class="table" role="table">
      <thead>
        <tr><th>ID</th><th>Time</th><th>Name</th><th>Email</th><th>USD</th><th>Crypto</th><th>Network</th><th>Address</th></tr>
      </thead>
      <tbody>
      <?php if (empty($donations)): ?>
        <tr><td colspan="8" style="text-align:center">No donations found</td></tr>
      <?php else: foreach ($donations as $d): ?>
        <tr>
          <td><?=htmlspecialchars($d['id'] ?? '')?></td>
          <td><?=htmlspecialchars($d['timestamp'] ?? '')?></td>
          <td><?=htmlspecialchars($d['name'] ?? '')?></td>
          <td><?=htmlspecialchars($d['email'] ?? '')?></td>
          <td><?=htmlspecialchars($d['usd_amount'] ?? '')?></td>
          <td><?=htmlspecialchars($d['crypto_amount'] ?? '')?></td>
          <td><?=htmlspecialchars($d['network'] ?? '')?></td>
          <td style="font-family:monospace;max-width:280px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?=htmlspecialchars($d['address'] ?? '')?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>

    <div class="pager">
      <?php
        $pages = max(1, ceil($total / $perPage));
        for ($i=1;$i<=$pages;$i++){
          if ($i === $page) echo "<strong>$i</strong> ";
          else {
            $q = $search ? '&q=' . urlencode($search) : '';
            echo "<a href=\"admin.php?page={$i}{$q}\">{$i}</a> ";
          }
        }
      ?>
    </div>
  </div>
<?php endif; ?>

</div>
</body>
</html>