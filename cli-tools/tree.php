<?php

require __DIR__ . '/../vendor/autoload.php';

use Inilim\Tool\Path;
use Inilim\Tool\VD;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class TreeBuilder
{
    /**
     * @param string   $root            Корневая папка
     * @param string[] $ignoredDirs     Имена папок для игнорирования (например: ['vendor', 'node_modules'])
     * @param string[] $ignoredFormats  Расширения файлов для игнорирования (например: ['log', 'tmp'])
     * @param string[] $ignoredFiles    Имена файлов для игнорирования (например: ['.env', 'composer.lock'])
     * @param bool     $showFiles       Показывать ли файлы (иначе только дерево папок)
     */
    public function __construct(
        private readonly string $root,
        private readonly array $ignoredDirs = [],
        private readonly array $ignoredFormats = [],
        private readonly array $ignoredFiles = [],
        private readonly bool $showFiles = true,
    ) {}

    public function build(): string
    {
        if (!is_dir($this->root)) {
            throw new \InvalidArgumentException("Директория не найдена: {$this->root}");
        }

        $finder = new Finder();
        $finder
            ->files()
            ->in($this->root)
            ->ignoreDotFiles(false)
            ->ignoreVCS(true);

        // Исключаем папки
        foreach ($this->ignoredDirs as $dir) {
            $finder->notPath('#(^|/)' . preg_quote($dir, '#') . '(/|$)#');
        }

        // Исключаем форматы (по расширению)
        foreach ($this->ignoredFormats as $ext) {
            $ext = ltrim($ext, '.');
            $finder->notName('/\.' . preg_quote($ext, '/') . '$/i');
        }

        // Исключаем конкретные имена файлов
        foreach ($this->ignoredFiles as $fileName) {
            // точное совпадение имени файла в любой папке
            $finder->notName('/^' . preg_quote($fileName, '/') . '$/');
        }

        // Собираем все пути (файлы + папки)
        $paths = [];
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $parts = explode('/', $relative);

            // Добавляем все промежуточные папки
            $acc = '';
            foreach ($parts as $i => $part) {
                $acc = $acc === '' ? $part : $acc . '/' . $part;
                $isFile = $i === count($parts) - 1;
                if (!$isFile || $this->showFiles) {
                    $paths[$acc] = $isFile ? 'file' : 'dir';
                }
            }
        }

        ksort($paths);

        return $this->render($paths);
    }

    private function render(array $paths): string
    {
        $out = basename($this->root) . "/\n";
        $this->renderLevel($out, $paths, '', '');

        return $out;
    }

    private function renderLevel(string &$out, array $paths, string $prefix, string $parent): void
    {
        $children = [];
        foreach ($paths as $path => $type) {
            $parentDir = dirname($path);
            if ($parentDir === '.' || $parentDir === '') {
                $parentDir = '';
            }
            if ($parentDir === $parent && ($parent !== '' || !str_contains($path, '/'))) {
                $children[$path] = $type;
            }
        }

        // Сортируем: папки сначала, затем файлы, по алфавиту
        uksort($children, function ($a, $b) use ($children) {
            $ta = $children[$a] === 'dir' ? 0 : 1;
            $tb = $children[$b] === 'dir' ? 0 : 1;
            return $ta !== $tb ? $ta <=> $tb : strcasecmp(basename($a), basename($b));
        });

        $i = 0;
        $count = count($children);
        foreach ($children as $path => $type) {
            $i++;
            $isLast = $i === $count;
            $name = basename($path);
            $branch = $isLast ? '└── ' : '├── ';
            $suffix = $type === 'dir' ? '/' : '';

            $out .= $prefix . $branch . $name . $suffix . "\n";

            if ($type === 'dir') {
                $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
                $this->renderLevel($out, $paths, $newPrefix, $path);
            }
        }
    }
}

// ==================== Использование ====================

$root = $argv[1] ?? __DIR__;
$root = Path::realPath($root);


$tree = new TreeBuilder(
    root: $root,
    ignoredDirs: ['vendor', '.git', 'cli-tools', '.aicoder', 'files',],
    ignoredFormats: [],
    ignoredFiles: ['.env', '.env.local', '.gitignore', 'composer.lock', '.gitkeep', 'tree.out',],
    showFiles: true,
);

VD::d('Структура проекта:');

echo $tree->build();
