<?php

declare(strict_types=1);

namespace ZPMLabs\LaravelI18nAudit\Scanners;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZPMLabs\LaravelI18nAudit\Support\PathNormalizer;
use ZPMLabs\LaravelI18nAudit\Support\ScanResult;

final class PhpFunctionCallScanner implements SourceScannerInterface
{
    private Parser $parser;

    private Standard $printer;

    public function __construct()
    {
        $factory = new ParserFactory();

        if (method_exists($factory, 'createForHostVersion')) {
            /** @var Parser $parser */
            $parser = $factory->createForHostVersion();
            $this->parser = $parser;
        } else {
            /** @var Parser $parser */
            $parser = $factory->createForNewestSupportedVersion();
            $this->parser = $parser;
        }

        $this->printer = new Standard();
    }

    public function scan(array $paths, array $excludePaths, bool $followSymlinks = false): ScanResult
    {
        $result = new ScanResult();
        $normalizedExcludes = array_map(PathNormalizer::normalize(...), $excludePaths);

        foreach ($paths as $path) {
            $realPath = realpath($path);

            if ($realPath === false || !is_dir($realPath)) {
                continue;
            }

            $flags = RecursiveDirectoryIterator::SKIP_DOTS;

            if ($followSymlinks) {
                $flags |= RecursiveDirectoryIterator::FOLLOW_SYMLINKS;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realPath, $flags));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $normalizedPath = PathNormalizer::normalize($file->getPathname());

                if ($this->shouldExclude($normalizedPath, $normalizedExcludes)) {
                    continue;
                }

                if (!str_ends_with($normalizedPath, '.php') || str_ends_with($normalizedPath, '.blade.php')) {
                    continue;
                }

                $this->scanFile($normalizedPath, $result);
            }
        }

        return $result;
    }

    private function scanFile(string $filePath, ScanResult $result): void
    {
        $code = file_get_contents($filePath);

        if ($code === false || trim($code) === '') {
            return;
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (Error) {
            return;
        }

        if ($ast === null) {
            return;
        }

        $traverser = new NodeTraverser();
        $printer = $this->printer;

        $traverser->addVisitor(new class ($result, $filePath, $printer, $code) extends NodeVisitorAbstract {
            public function __construct(
                private readonly ScanResult $result,
                private readonly string $filePath,
                private readonly Standard $printer,
                private readonly string $code,
            ) {
            }

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Expr\FuncCall) {
                    $this->handleFunctionCall($node);
                }

                if ($node instanceof Node\Expr\StaticCall) {
                    $this->handleStaticCall($node);
                }

                return null;
            }

            private function handleFunctionCall(Node\Expr\FuncCall $node): void
            {
                if (!$node->name instanceof Node\Name) {
                    return;
                }

                $name = strtolower($node->name->toString());

                if (!in_array($name, ['__', 'trans', 'trans_choice'], true)) {
                    return;
                }

                $this->captureFirstArg($node->args[0]->value ?? null, "{$name}()");
            }

            private function handleStaticCall(Node\Expr\StaticCall $node): void
            {
                if (!$node->class instanceof Node\Name || !$node->name instanceof Node\Identifier) {
                    return;
                }

                $class = ltrim(strtolower($node->class->toString()), '\\');
                $method = strtolower($node->name->toString());

                if (!in_array($method, ['get', 'choice'], true)) {
                    return;
                }

                if (!in_array($class, ['lang', 'illuminate\\support\\facades\\lang'], true)) {
                    return;
                }

                $this->captureFirstArg($node->args[0]->value ?? null, "Lang::{$method}()");
            }

            private function captureFirstArg(?Node\Expr $argument, string $source): void
            {
                if ($argument instanceof Node\Scalar\String_) {
                    $line = $argument->getStartLine();
                    $filePos = $argument->getStartFilePos();
                    $char = is_int($filePos) ? $filePos + 1 : 1;
                    $column = is_int($filePos) ? $this->columnFromOffset($this->code, $filePos) : 1;

                    $this->result->addUsedKey(
                        $argument->value,
                        $this->filePath,
                        $line,
                        $column,
                        $char,
                        $source
                    );
                    return;
                }

                if ($argument === null) {
                    return;
                }

                $line = $argument->getStartLine();
                $expression = $this->printer->prettyPrintExpr($argument);
                $this->result->addDynamicKey($this->filePath, $line, $expression, $source);
            }

            private function columnFromOffset(string $content, int $offset): int
            {
                if ($offset <= 0) {
                    return 1;
                }

                $prefix = substr($content, 0, $offset);

                if ($prefix === false) {
                    return 1;
                }

                $lastNewlinePos = strrpos($prefix, "\n");

                if ($lastNewlinePos === false) {
                    return $offset + 1;
                }

                return $offset - $lastNewlinePos;
            }
        });

        $traverser->traverse($ast);
    }

    /**
     * @param array<int, string> $normalizedExcludes
     */
    private function shouldExclude(string $filePath, array $normalizedExcludes): bool
    {
        $normalizedFile = PathNormalizer::normalize($filePath);

        foreach ($normalizedExcludes as $exclude) {
            $normalizedExclude = trim(PathNormalizer::normalize($exclude));

            if ($normalizedExclude === '') {
                continue;
            }

            if (str_contains(strtolower($normalizedFile), strtolower($normalizedExclude))) {
                return true;
            }
        }

        return false;
    }
}
