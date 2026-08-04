<?php

declare(strict_types=1);

namespace Kode\Attributes;

use Generator;
use Kode\Attributes\Exception\InvalidTargetException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * 静态属性扫描器。
 *
 * 提供目录扫描功能，用于发现带有特定属性的类。
 * 适用于 CLI 工具、路由注册、事件发现等编译期分析场景。
 *
 * 2.0 修复：
 * - 支持 `enum` 声明（1.x 只识别 class/interface/trait，枚举全部漏扫）
 * - 正确跳过匿名类 `new class {...}` 与 `Foo::class` 常量表达式（1.x 会解析出错误类名）
 * - 支持在构造时指定默认目录，`Attr::scan($dir)` 不再丢失参数
 * - 记录跳过原因，避免"扫不到"却无从排查
 *
 * @package Kode\Attributes
 * @author KodePHP <382601296@qq.com>
 */
final class Scanner
{
    /**
     * 属性读取器实例。
     */
    private Reader $reader;

    /**
     * 默认扫描目录。
     */
    private ?string $baseDir;

    /**
     * 排除模式列表。
     *
     * @var array<int, string>
     */
    private array $excludes = [];

    /**
     * 包含模式列表。
     *
     * @var array<int, string>
     */
    private array $includes = [];

    /**
     * 文件扩展名过滤。
     */
    private string $extension = '.php';

    /**
     * 是否在类未自动加载时尝试 require 文件。
     */
    private bool $autoload = false;

    /**
     * 扫描过程中跳过的目标及原因。
     *
     * @var array<string, string>
     */
    private array $skipped = [];

    /**
     * 创建新的 Scanner 实例。
     *
     * @param Reader $reader 属性读取器实例
     * @param string|null $dir 默认扫描目录
     */
    public function __construct(Reader $reader, ?string $dir = null)
    {
        $this->reader = $reader;
        $this->baseDir = $dir;
    }

    /**
     * 设置默认扫描目录。
     */
    public function in(string $dir): self
    {
        $this->baseDir = $dir;

        return $this;
    }

    /**
     * 设置要扫描的文件扩展名。
     *
     * @param string $extension 文件扩展名（如 .php）
     */
    public function extension(string $extension): self
    {
        $this->extension = str_starts_with($extension, '.') ? $extension : '.' . $extension;

        return $this;
    }

    /**
     * 添加排除模式。
     *
     * @param string ...$patterns 要排除的模式（支持通配符）
     */
    public function exclude(string ...$patterns): self
    {
        $this->excludes = array_merge($this->excludes, $patterns);

        return $this;
    }

    /**
     * 添加包含模式。
     *
     * @param string ...$patterns 要包含的模式（支持通配符）
     */
    public function include(string ...$patterns): self
    {
        $this->includes = array_merge($this->includes, $patterns);

        return $this;
    }

    /**
     * 是否允许在类无法自动加载时 require 源文件。
     *
     * 默认关闭：执行未知文件存在副作用风险，仅在可信目录下开启。
     */
    public function autoload(bool $autoload = true): self
    {
        $this->autoload = $autoload;

        return $this;
    }

    /**
     * 获取本次扫描中被跳过的目标及原因。
     *
     * @return array<string, string> 类名 => 原因
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    /**
     * 列出目录下声明的所有类型名（不触发属性读取）。
     *
     * @param string|null $dir 目录，省略时使用默认目录
     * @return array<int, string> 类型名数组
     */
    public function classes(?string $dir = null): array
    {
        $classes = [];

        foreach ($this->files($dir) as $path) {
            foreach (self::parseTypes($path) as $class) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * 扫描目录获取所有带有类级属性的类。
     *
     * @param string|null $dir 要扫描的目录路径，省略时使用默认目录
     * @return Generator<string, MetaList> 类名 => 属性集合
     */
    public function scan(?string $dir = null): Generator
    {
        $this->skipped = [];

        foreach ($this->files($dir) as $path) {
            foreach (self::parseTypes($path) as $class) {
                if (!$this->ensureLoaded($class, $path)) {
                    continue;
                }

                try {
                    $metas = $this->reader->getClassAttrs($class);
                } catch (Throwable $e) {
                    $this->skipped[$class] = $e->getMessage();

                    continue;
                }

                if ($metas->isNotEmpty()) {
                    yield $class => $metas;
                }
            }
        }
    }

    /**
     * 查找带有特定属性的类。
     *
     * @param string $dirOrAttr 目录路径；若只传一个参数则视为属性类名并使用默认目录
     * @param string|null $attrClass 要查找的属性类名
     * @return Generator<string, MetaList> 类名 => 匹配到的属性集合
     */
    public function find(string $dirOrAttr, ?string $attrClass = null): Generator
    {
        [$dir, $attr] = $this->resolveFindArgs($dirOrAttr, $attrClass);

        foreach ($this->scan($dir) as $class => $metas) {
            $matched = $metas->getAll($attr);

            if ($matched->isNotEmpty()) {
                yield $class => $matched;
            }
        }
    }

    /**
     * 查找带有特定属性的所有类（返回完整元数据）。
     *
     * @param string $dirOrAttr 目录路径或属性类名
     * @param string|null $attrClass 要查找的属性类名
     * @return Generator<string, MetaList> 类名 => 完整属性集合
     */
    public function findWithAll(string $dirOrAttr, ?string $attrClass = null): Generator
    {
        [$dir, $attr] = $this->resolveFindArgs($dirOrAttr, $attrClass);

        foreach ($this->scan($dir) as $class => $metas) {
            if ($metas->has($attr)) {
                yield $class => $metas;
            }
        }
    }

    /**
     * 扫描目录获取所有类的属性信息（类 + 方法 + 属性 + 常量）。
     *
     * @param string|null $dir 要扫描的目录路径
     * @param bool $inherited 是否沿继承链读取
     * @return Generator<string, array{class: MetaList, methods: array<string, MetaList>, properties: array<string, MetaList>, constants: array<string, MetaList>}>
     */
    public function scanDeep(?string $dir = null, bool $inherited = false): Generator
    {
        $this->skipped = [];

        foreach ($this->files($dir) as $path) {
            foreach (self::parseTypes($path) as $class) {
                if (!$this->ensureLoaded($class, $path)) {
                    continue;
                }

                try {
                    $classAttrs = $this->reader->getClassAttrs($class, $inherited);
                    $methodAttrs = $this->reader->getAllMethodAttrs($class, $inherited);
                    $propertyAttrs = $this->reader->getAllPropertyAttrs($class, $inherited);
                    $constantAttrs = $this->reader->getAllConstantAttrs($class);
                } catch (Throwable $e) {
                    $this->skipped[$class] = $e->getMessage();

                    continue;
                }

                if ($classAttrs->isNotEmpty() || $methodAttrs !== [] || $propertyAttrs !== [] || $constantAttrs !== []) {
                    yield $class => [
                        'class' => $classAttrs,
                        'methods' => $methodAttrs,
                        'properties' => $propertyAttrs,
                        'constants' => $constantAttrs,
                    ];
                }
            }
        }
    }

    /**
     * 遍历目录下符合条件的文件路径。
     *
     * @param string|null $dir 目录
     * @return Generator<int, string> 文件绝对路径
     */
    private function files(?string $dir): Generator
    {
        $target = $dir ?? $this->baseDir;

        if ($target === null) {
            throw new InvalidTargetException('未指定扫描目录：请在 Attr::scan($dir) 或 scan($dir) 中提供目录路径。');
        }

        $realDir = realpath($target);

        if ($realDir === false || !is_dir($realDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $suffix = strtolower($this->extension);

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            if (!str_ends_with(strtolower($path), $suffix)) {
                continue;
            }

            if ($this->shouldExclude($path)) {
                continue;
            }

            if ($this->includes !== [] && !$this->shouldInclude($path)) {
                continue;
            }

            yield $path;
        }
    }

    /**
     * 解析 find()/findWithAll() 的重载参数。
     *
     * @return array{0: string|null, 1: string}
     */
    private function resolveFindArgs(string $dirOrAttr, ?string $attrClass): array
    {
        if ($attrClass !== null) {
            return [$dirOrAttr, $attrClass];
        }

        return [$this->baseDir, $dirOrAttr];
    }

    /**
     * 确保类型可用，必要时按需加载源文件。
     */
    private function ensureLoaded(string $class, string $path): bool
    {
        if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
            return true;
        }

        if ($this->autoload) {
            try {
                require_once $path;
            } catch (Throwable $e) {
                $this->skipped[$class] = '加载文件失败：' . $e->getMessage();

                return false;
            }

            if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
                return true;
            }
        }

        $this->skipped[$class] = sprintf('类型未加载（%s），请检查 PSR-4 映射或开启 autoload()。', $path);

        return false;
    }

    /**
     * 检查文件路径是否应该被排除。
     */
    private function shouldExclude(string $path): bool
    {
        foreach ($this->excludes as $pattern) {
            if (self::matchPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查文件路径是否应该被包含。
     */
    private function shouldInclude(string $path): bool
    {
        foreach ($this->includes as $pattern) {
            if (self::matchPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 匹配路径模式。
     */
    private static function matchPattern(string $path, string $pattern): bool
    {
        if (str_contains($pattern, '*')) {
            $regex = '/' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '/i';

            return preg_match($regex, $path) === 1;
        }

        return str_contains($path, $pattern);
    }

    /**
     * 从 PHP 文件中解析出所有具名类型（class / interface / trait / enum）。
     *
     * 会正确跳过：
     * - `new class {...}` 匿名类
     * - `Foo::class` 常量表达式
     * - `$obj instanceof class` 之类的非声明位置
     *
     * @param string $file 文件路径
     * @return array<int, string> 完全限定类型名数组
     */
    private static function parseTypes(string $file): array
    {
        $content = @file_get_contents($file);

        if ($content === false || !str_contains($content, '<?php')) {
            return [];
        }

        $tokens = @token_get_all($content);

        if ($tokens === false) {
            return [];
        }

        $types = [];
        $namespace = '';
        $count = count($tokens);
        $previous = null;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                $previous = $token;

                continue;
            }

            if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = self::parseNamespace($tokens, $i + 1, $count);
                $previous = $token;

                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                // 跳过 `new class`（匿名类）与 `Foo::class`
                $skip = is_array($previous)
                    ? in_array($previous[0], [T_NEW, T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)
                    : false;

                if (!$skip) {
                    $name = self::parseTypeName($tokens, $i + 1, $count);

                    if ($name !== null) {
                        $types[] = $namespace !== '' ? $namespace . '\\' . $name : $name;
                    }
                }
            }

            $previous = $token;
        }

        return $types;
    }

    /**
     * 解析命名空间。
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Token 数组
     * @param int $start 开始索引
     * @param int $limit 限制数量
     */
    private static function parseNamespace(array $tokens, int $start, int $limit): string
    {
        $namespace = '';

        for ($i = $start; $i < $limit; $i++) {
            $token = $tokens[$i] ?? null;

            if (!is_array($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }

                continue;
            }

            if ($token[0] === T_WHITESPACE) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)) {
                $namespace .= $token[1];

                continue;
            }

            break;
        }

        return trim($namespace, '\\');
    }

    /**
     * 解析类型名。
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Token 数组
     * @param int $start 开始索引
     * @param int $limit 限制数量
     */
    private static function parseTypeName(array $tokens, int $start, int $limit): ?string
    {
        for ($i = $start; $i < $limit; $i++) {
            $token = $tokens[$i] ?? null;

            if (!is_array($token)) {
                return null;
            }

            if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }

            return $token[0] === T_STRING ? $token[1] : null;
        }

        return null;
    }
}
