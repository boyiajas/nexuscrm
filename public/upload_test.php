<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'files' => $_FILES,
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'sys_get_temp_dir' => sys_get_temp_dir(),
        'upload_tmp_dir' => ini_get('upload_tmp_dir'),
        'is_writable_tmp' => is_writable(sys_get_temp_dir()),
    ]);
    exit;
}
?>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_file">
    <button type="submit">Upload</button>
</form>
