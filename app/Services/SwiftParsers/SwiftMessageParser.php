<?php

namespace App\Services\SwiftParsers;

interface SwiftMessageParser
{
    /**
     * Parses the raw .fin content into a structured array.
     * @param string $finContent
     * @return array
     */
    public function parse(string $finContent): array;

    /**
     * Converts the structured array into a CSV string.
     * @param array $data
     * @return string
     */
    public function toCsv(array $data): string;
}