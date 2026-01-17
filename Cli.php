<?php

//declare(strict_types=1);

namespace cryodrift\printrtojson;

use cryodrift\fw\Context;
use cryodrift\fw\Core;
use cryodrift\fw\interface\Handler;
use cryodrift\fw\trait\CliHandler;
use cryodrift\fw\cli\ParamFile;

/**
 * Convert PHP print_r style Array(...) dumps into JSON
 */
class Cli implements Handler
{
    use CliHandler;

    public function handle(Context $ctx): Context
    {
        return $this->handleCli($ctx);
    }

    /**
     * @cli Convert print_r array text to JSON
     * @cli param: -in (stdin or pathname)
     * @cli param: [-out] (pathname to write JSON)
     */
    protected function run(ParamFile $in, ParamFile $out): string
    {
        $text = (string)$in;
        $json = $this->convert($text);

        if ($out->filename ?? '') {
            Core::fileWrite($out->filename, $json);
            return $out->filename;
        }
        return $json;
    }

    private function convert(string $text): string
    {
        // Normalize newlines
        $lines = preg_split('/\r?\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $result = [];
        $current =& $result;
        $stack = [];

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || $trim === '(') {
                continue;
            }

            if ($trim === ')') {
                if (!empty($stack)) {
                    $parent =& $stack[count($stack) - 1];
                    array_pop($stack);
                    $current =& $parent;
                }
                continue;
            }

            if (strpos($line, '=>') !== false) {
                [$left, $right] = explode('=>', $line, 2);
                $key = trim($left);
                $key = trim($key, "[] \t\r\n");
                $value = trim($right);

                if ($value === 'Array') {
                    if (!isset($current[$key]) || !is_array($current[$key])) {
                        $current[$key] = [];
                    }
                    $stack[] =& $current;
                    $current =& $current[$key];
                } else {
                    $v = $this->normalizeScalar($value);
                    $current[$key] = $v;
                }
            }
        }

        return  Core::jsonWrite($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function normalizeScalar(string $value): mixed
    {
        $v = $value;
        // strip trailing commas occasionally present
        $v = rtrim($v, ",");
        // strip surrounding quotes if present
        if ((strlen($v) >= 2) && ((($v[0] === '"') && (substr($v, -1) === '"')) || (($v[0] === "'") && (substr($v, -1) === "'")))) {
            $v = substr($v, 1, -1);
        }
        // cast numeric strings
        if (is_numeric($v)) {
            return (strpos($v, '.') !== false) ? (float)$v : (int)$v;
        }
        // bool/null words
        $lw = strtolower($v);
        if ($lw === 'true') return true;
        if ($lw === 'false') return false;
        if ($lw === 'null') return null;
        return $v;
    }
}
