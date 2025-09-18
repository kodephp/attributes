<?php

declare(strict_types=1);

namespace Kode\Attributes;

/**
 * Static attribute scanner.
 * 
 * Provides functionality for scanning directories for attributes
 * without loading the classes into memory.
 * 
 * @package Kode\Attributes
 */
final class Scanner
{
    private Reader $reader;
    private array $excludes = [];

    /**
     * Create a new Scanner instance.
     * 
     * @param Reader $reader The attribute reader to use
     */
    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Exclude patterns from scanning.
     * 
     * @param string ...$patterns The patterns to exclude
     * @return self
     */
    public function exclude(string ...$patterns): self
    {
        $this->excludes = array_merge($this->excludes, $patterns);
        return $this;
    }

    /**
     * Scan a directory for attributes.
     * 
     * @param string $dir The directory to scan
     * @return \Generator
     */
    public function scan(string $dir): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );
        
        $phpFiles = new \RegexIterator($iterator, '/\.php$/i');
        
        foreach ($phpFiles as $file) {
            if ($this->shouldExclude($file->getPathname())) {
                continue;
            }
            
            $classes = $this->getClassesFromFile($file->getPathname());
            
            foreach ($classes as $class) {
                try {
                    $metas = $this->reader->getClassAttrs($class);
                    if (count($metas) > 0) {
                        yield $class => $metas;
                    }
                } catch (\Throwable $e) {
                    // Skip classes that can't be instantiated
                    continue;
                }
            }
        }
    }

    /**
     * Find classes with a specific attribute.
     * 
     * @param string $attrClass The attribute class to look for
     * @return \Generator
     */
    public function find(string $attrClass): \Generator
    {
        // We need to check each class we scan for the specific attribute
        foreach ($this->scan('.') as $class => $metas) {
            foreach ($metas as $meta) {
                if ($meta->name === $attrClass) {
                    yield $class => $metas;
                    break;
                }
            }
        }
    }

    /**
     * Check if a file should be excluded from scanning.
     * 
     * @param string $path The file path
     * @return bool
     */
    private function shouldExclude(string $path): bool
    {
        foreach ($this->excludes as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get all classes defined in a PHP file.
     * 
     * @param string $file The file path
     * @return array
     */
    private function getClassesFromFile(string $file): array
    {
        $classes = [];
        $namespace = '';
        
        $tokens = token_get_all(file_get_contents($file));
        
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $i++;
                while (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                
                $namespaceParts = [];
                while (isset($tokens[$i]) && is_array($tokens[$i]) && 
                       ($tokens[$i][0] === T_STRING || $tokens[$i][0] === T_NS_SEPARATOR)) {
                    $namespaceParts[] = $tokens[$i][1];
                    $i++;
                }
                
                $namespace = implode('', $namespaceParts);
            }
            
            if ($tokens[$i][0] === T_CLASS) {
                $i++;
                while (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                
                if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $className = $tokens[$i][1];
                    $classes[] = $namespace ? $namespace . '\\' . $className : $className;
                }
            }
        }
        
        return $classes;
    }
}