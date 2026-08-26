<?php
$file = 'app/Http/Controllers/Api/ClientController.php';
$contents = file_get_contents($file);

// Find the start of validateImportFile
$startMethod = '    protected function validateImportFile(string $path, string $originalName): void';
$startPos = strpos($contents, $startMethod);

// Find the end of the class
$endPos = strrpos($contents, '}');

// Extract the methods
$methods = substr($contents, $startPos, $endPos - $startPos);

// The remaining contents
$newContents = substr($contents, 0, $startPos) . substr($contents, $endPos);

// Add the trait usage at the top of the class
$classDef = 'class ClientController extends Controller' . "\n" . '{';
$newContents = str_replace($classDef, $classDef . "\n    use \App\Traits\HasImportHelpers;\n", $newContents);

file_put_contents($file, $newContents);

$trait = "<?php\n\nnamespace App\Traits;\n\nuse Illuminate\Support\Str;\nuse ZipArchive;\nuse SimpleXMLElement;\n\ntrait HasImportHelpers\n{\n" . $methods . "\n}\n";
if (!is_dir('app/Traits')) mkdir('app/Traits');
file_put_contents('app/Traits/HasImportHelpers.php', $trait);

echo "Refactored successfully.";
