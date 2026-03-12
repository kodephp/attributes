<?php

declare(strict_types=1);

namespace Kode\Attributes;

use Generator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RegexIterator;
use SplFileInfo;

/**
 * 静态属性扫描器。
 * 
 * 提供目录扫描功能，用于发现带有特定属性的类。
 * 适用于CLI工具、路由注册、事件发现等编译期分析场景。
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
     * 排除模式列表。
     * 
     * @var array<string>
     */
    private array $excludes = [];

    /**
     * 包含模式列表。
     * 
     * @var array<string>
     */
    private array $includes = [];

    /**
     * 文件扩展名过滤。
     *
     * @var string
     */
    private string $extension = '.php';

    /**
     * 创建新的Scanner实例。
     * 
     * @param Reader $reader 属性读取器实例
     */
    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * 设置要扫描的文件扩展名。
     * 
     * @param string $extension 文件扩展名（如 .php）
     * @return self
     */
    public function extension(string $extension): self
    {
        $this->extension = $extension;
        return $this;
    }

    /**
     * 添加排除模式。
     * 
     * @param string ...$patterns 要排除的模式（支持通配符）
     * @return self
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
     * @return self
     */
    public function include(string ...$patterns): self
    {
        $this->includes = array_merge($this->includes, $patterns);
        return $this;
    }

    /**
     * 扫描目录获取所有带有属性的类。
     * 
     * @param string $dir 要扫描的目录路径
     * @return Generator<string, MetaList> 类名 => 属性集合
     */
    public function scan(string $dir): Generator
    {
        $realDir = realpath($dir);
        if ($realDir === false || !is_dir($realDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $extensionPattern = '/' . preg_quote($this->extension, '/') . '$/i';
        $phpFiles = new RegexIterator($iterator, $extensionPattern);
        
        foreach ($phpFiles as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $path = $file->getPathname();
            
            if ($this->shouldExclude($path)) {
                continue;
            }

            if ($this->includes !== [] && !$this->shouldInclude($path)) {
                continue;
            }
            
            $classes = $this->getClassesFromFile($path);
            
            foreach ($classes as $class) {
                try {
                    $metas = $this->reader->getClassAttrs($class);
                    if ($metas->isNotEmpty()) {
                        yield $class => $metas;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
    }

    /**
     * 查找带有特定属性的类。
     * 
     * @param string $dir 要扫描的目录路径
     * @param string $attrClass 要查找的属性类名
     * @return Generator<string, MetaList> 类名 => 属性集合
     */
    public function find(string $dir, string $attrClass): Generator
    {
        foreach ($this->scan($dir) as $class => $metas) {
            if ($metas->has($attrClass)) {
                yield $class => $metas->getAll($attrClass);
            }
        }
    }

    /**
     * 查找带有特定属性的所有类（返回完整元数据）。
     * 
     * @param string $dir 要扫描的目录路径
     * @param string $attrClass 要查找的属性类名
     * @return Generator<string, MetaList> 类名 => 完整属性集合
     */
    public function findWithAll(string $dir, string $attrClass): Generator
    {
        foreach ($this->scan($dir) as $class => $metas) {
            if ($metas->has($attrClass)) {
                yield $class => $metas;
            }
        }
    }

    /**
     * 扫描目录获取所有类的属性信息（包括方法、属性等）。
     * 
     * @param string $dir 要扫描的目录路径
     * @return Generator<string, array{class: MetaList, methods: array<string, MetaList>, properties: array<string, MetaList>}> 类名 => 属性信息
     */
    public function scanDeep(string $dir): Generator
    {
        $realDir = realpath($dir);
        if ($realDir === false || !is_dir($realDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $extensionPattern = '/' . preg_quote($this->extension, '/') . '$/i';
        $phpFiles = new RegexIterator($iterator, $extensionPattern);
        
        foreach ($phpFiles as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $path = $file->getPathname();
            
            if ($this->shouldExclude($path)) {
                continue;
            }

            if ($this->includes !== [] && !$this->shouldInclude($path)) {
                continue;
            }
            
            $classes = $this->getClassesFromFile($path);
            
            foreach ($classes as $class) {
                try {
                    $classAttrs = $this->reader->getClassAttrs($class);
                    $methodAttrs = $this->reader->getAllMethodAttrs($class);
                    $propertyAttrs = $this->reader->getAllPropertyAttrs($class);
                    
                    if ($classAttrs->isNotEmpty() || !empty($methodAttrs) || !empty($propertyAttrs)) {
                        yield $class => [
                            'class' => $classAttrs,
                            'methods' => $methodAttrs,
                            'properties' => $propertyAttrs,
                        ];
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
    }

    /**
     * 检查文件路径是否应该被排除。
     * 
     * @param string $path 文件路径
     * @return bool 是否排除
     */
    private function shouldExclude(string $path): bool
    {
        foreach ($this->excludes as $pattern) {
            if ($this->matchPattern($path, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 检查文件路径是否应该被包含。
     * 
     * @param string $path 文件路径
     * @return bool 是否包含
     */
    private function shouldInclude(string $path): bool
    {
        if ($this->includes === []) {
            return true;
        }

        foreach ($this->includes as $pattern) {
            if ($this->matchPattern($path, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 匹配路径模式。
     * 
     * @param string $path 文件路径
     * @param string $pattern 模式
     * @return bool 是否匹配
     */
    private function matchPattern(string $path, string $pattern): bool
    {
        if (str_contains($pattern, '*')) {
            $regex = '/' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '/i';
            return preg_match($regex, $path) === 1;
        }
        
        return str_contains($path, $pattern);
    }

    /**
     * 从PHP文件中获取所有类名。
     * 
     * @param string $file 文件路径
     * @return array<string> 类名数组
     */
    private function getClassesFromFile(string $file): array
    {
        $classes = [];
        $namespace = '';
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $tokens = @token_get_all($content);
        if ($tokens === false) {
            return [];
        }
        
        $tokenCount = count($tokens);
        
        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];
            
            if (!is_array($token)) {
                continue;
            }
            
            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->parseNamespace($tokens, $i + 1, $tokenCount);
            }
            
            if ($token[0] === T_CLASS || $token[0] === T_INTERFACE || $token[0] === T_TRAIT) {
                $className = $this->parseClassName($tokens, $i + 1, $tokenCount);
                if ($className !== null) {
                    $fullClassName = $namespace !== '' ? $namespace . '\\' . $className : $className;
                    $classes[] = $fullClassName;
                }
            }
        }
        
        return $classes;
    }

    /**
     * 解析命名空间。
     * 
     * @param array $tokens Token数组
     * @param int $start 开始索引
     * @param int $limit 限制数量
     * @return string 命名空间
     */
    private function parseNamespace(array $tokens, int $start, int $limit): string
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
            
            if ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR) {
                $namespace .= $token[1];
            } elseif ($token[0] === T_NAME_QUALIFIED) {
                $namespace .= $token[1];
            } else {
                break;
            }
        }
        
        return $namespace;
    }

    /**
     * 解析类名。
     * 
     * @param array $tokens Token数组
     * @param int $start 开始索引
     * @param int $limit 限制数量
     * @return string|null 类名
     */
    private function parseClassName(array $tokens, int $start, int $limit): ?string
    {
        for ($i = $start; $i < $limit; $i++) {
            $token = $tokens[$i] ?? null;
            
            if (!is_array($token)) {
                continue;
            }
            
            if ($token[0] === T_WHITESPACE) {
                continue;
            }
            
            if ($token[0] === T_STRING) {
                return $token[1];
            }
            
            break;
        }
        
        return null;
    }
}
