<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Parser Turtle (subset RDF 1.1 Turtle) tanpa dependensi eksternal.
 *
 * Mendukung @prefix/@base, prefixed name, IRI, literal string (termasuk
 * multiline), angka, boolean, typed/language literal, blank node, dan
 * RDF collection. Hasilnya berupa daftar triple yang siap dipetakan ke
 * model relasional.
 */
class TurtleParser
{
    public const RDF_TYPE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

    public const RDF_FIRST = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first';

    public const RDF_REST = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest';

    public const RDF_NIL = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil';

    private const XSD = 'http://www.w3.org/2001/XMLSchema#';

    /** @var array<string, string> */
    private array $prefixes = [];

    private ?string $base = null;

    /**
     * @var list<array{
     *     subject: string,
     *     predicate: string,
     *     object: string,
     *     object_type: 'iri'|'literal'|'bnode',
     *     datatype: string|null,
     *     language: string|null
     * }>
     */
    private array $triples = [];

    private string $input = '';

    private int $length = 0;

    private int $position = 0;

    private int $blankNodeCounter = 0;

    /**
     * @return list<array{
     *     subject: string,
     *     predicate: string,
     *     object: string,
     *     object_type: 'iri'|'literal'|'bnode',
     *     datatype: string|null,
     *     language: string|null
     * }>
     */
    public function parseFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("File Turtle tidak ditemukan atau tidak dapat dibaca: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("File Turtle gagal dibaca: {$path}");
        }

        return $this->parse($contents);
    }

    /**
     * @return list<array{
     *     subject: string,
     *     predicate: string,
     *     object: string,
     *     object_type: 'iri'|'literal'|'bnode',
     *     datatype: string|null,
     *     language: string|null
     * }>
     */
    public function parse(string $turtle): array
    {
        $this->prefixes = [];
        $this->base = null;
        $this->triples = [];
        $this->input = $this->normalize($turtle);
        $this->length = strlen($this->input);
        $this->position = 0;
        $this->blankNodeCounter = 0;

        while (! $this->eof()) {
            $this->skipWhitespaceAndComments();

            if ($this->eof()) {
                break;
            }

            if ($this->current() === '@') {
                $this->parseAtDirective();

                continue;
            }

            if ($this->startsWithKeyword('PREFIX')) {
                $this->parseSparqlPrefix();

                continue;
            }

            if ($this->startsWithKeyword('BASE')) {
                $this->parseSparqlBase();

                continue;
            }

            $this->parseTriples();
        }

        return $this->triples;
    }

    private function parseAtDirective(): void
    {
        $this->expect('@');
        $name = strtolower($this->readWord());

        $this->skipWhitespaceAndComments();

        if ($name === 'prefix') {
            $prefix = $this->readPrefixLabel();
            $this->skipWhitespaceAndComments();
            $iri = $this->readIri();
            $this->skipWhitespaceAndComments();
            $this->expect('.');

            $this->prefixes[$prefix] = $iri;

            return;
        }

        if ($name === 'base') {
            $this->base = $this->readIri();
            $this->skipWhitespaceAndComments();
            $this->expect('.');

            return;
        }

        throw $this->error("Direktif @{$name} tidak didukung");
    }

    private function parseSparqlPrefix(): void
    {
        $this->position += strlen('PREFIX');
        $this->skipWhitespaceAndComments();
        $prefix = $this->readPrefixLabel();
        $this->skipWhitespaceAndComments();
        $iri = $this->readIri();

        $this->prefixes[$prefix] = $iri;
    }

    private function parseSparqlBase(): void
    {
        $this->position += strlen('BASE');
        $this->skipWhitespaceAndComments();
        $this->base = $this->readIri();
    }

    private function parseTriples(): void
    {
        $subject = $this->parseSubject();

        $this->parsePredicateObjectList($subject);

        $this->skipWhitespaceAndComments();
        $this->expect('.');
    }

    private function parseSubject(): string
    {
        $this->skipWhitespaceAndComments();

        return match ($this->current()) {
            '[' => $this->parseBlankNodePropertyList(),
            '(' => $this->parseCollection(),
            '<' => $this->readIri(),
            default => $this->readNodeName(),
        };
    }

    private function parsePredicate(): string
    {
        $this->skipWhitespaceAndComments();

        if ($this->current() === '<') {
            return $this->readIri();
        }

        if ($this->startsWithKeyword('a')) {
            $this->advance();

            return self::RDF_TYPE;
        }

        return $this->readNodeName();
    }

    private function parsePredicateObjectList(string $subject): void
    {
        while (true) {
            $this->skipWhitespaceAndComments();

            if ($this->eof()) {
                throw $this->error('Predikat diharapkan setelah subjek');
            }

            $predicate = $this->parsePredicate();

            while (true) {
                $this->skipWhitespaceAndComments();
                $object = $this->parseObject();
                $this->addTriple($subject, $predicate, $object);

                $this->skipWhitespaceAndComments();

                if ($this->current() === ',') {
                    $this->advance();

                    continue;
                }

                break;
            }

            $this->skipWhitespaceAndComments();

            if ($this->current() !== ';') {
                break;
            }

            $this->advance();
            $this->skipWhitespaceAndComments();

            if ($this->eof() || $this->current() === '.' || $this->current() === ']') {
                break;
            }
        }
    }

    /**
     * @return array{value: string, type: 'iri'|'literal'|'bnode', datatype: string|null, language: string|null}
     */
    private function parseObject(): array
    {
        $this->skipWhitespaceAndComments();

        $char = $this->current();

        if ($char === '"' || $char === "'") {
            return $this->parseStringLiteral();
        }

        if ($char === '<') {
            return [
                'value' => $this->readIri(),
                'type' => 'iri',
                'datatype' => null,
                'language' => null,
            ];
        }

        if ($char === '[') {
            return [
                'value' => $this->parseBlankNodePropertyList(),
                'type' => 'bnode',
                'datatype' => null,
                'language' => null,
            ];
        }

        if ($char === '(') {
            return [
                'value' => $this->parseCollection(),
                'type' => 'bnode',
                'datatype' => null,
                'language' => null,
            ];
        }

        $token = $this->readToken();

        if ($token === '') {
            throw $this->error('Objek diharapkan');
        }

        if ($token === 'true' || $token === 'false') {
            return [
                'value' => $token,
                'type' => 'literal',
                'datatype' => self::XSD.'boolean',
                'language' => null,
            ];
        }

        if (preg_match('/^[+-]?\d+$/', $token) === 1) {
            return [
                'value' => $token,
                'type' => 'literal',
                'datatype' => self::XSD.'integer',
                'language' => null,
            ];
        }

        if (preg_match('/^[+-]?\d+\.\d+$/', $token) === 1) {
            return [
                'value' => $token,
                'type' => 'literal',
                'datatype' => self::XSD.'decimal',
                'language' => null,
            ];
        }

        if (preg_match('/^[+-]?(?:\d+\.\d*|\.\d+|\d+)[eE][+-]?\d+$/', $token) === 1) {
            return [
                'value' => $token,
                'type' => 'literal',
                'datatype' => self::XSD.'double',
                'language' => null,
            ];
        }

        if (str_starts_with($token, '_:')) {
            return [
                'value' => $token,
                'type' => 'bnode',
                'datatype' => null,
                'language' => null,
            ];
        }

        return [
            'value' => $this->expandPrefixedName($token),
            'type' => 'iri',
            'datatype' => null,
            'language' => null,
        ];
    }

    /**
     * @return array{value: string, type: 'literal', datatype: string|null, language: string|null}
     */
    private function parseStringLiteral(): array
    {
        $quote = $this->current();
        $delimiter = substr($this->input, $this->position, 3) === str_repeat($quote, 3)
            ? str_repeat($quote, 3)
            : $quote;

        for ($i = 0; $i < strlen($delimiter); $i++) {
            $this->advance();
        }

        $value = '';

        while (! $this->eof()) {
            if (substr($this->input, $this->position, strlen($delimiter)) === $delimiter) {
                for ($i = 0; $i < strlen($delimiter); $i++) {
                    $this->advance();
                }

                break;
            }

            $char = $this->current();

            if ($char === '\\') {
                $value .= $this->readEscape();

                continue;
            }

            if (strlen($delimiter) === 1 && ($char === "\n" || $char === "\r")) {
                throw $this->error('Literal string tidak boleh berisi baris baru; gunakan triple quote');
            }

            $value .= $this->advance();
        }

        if ($this->eof() && substr($this->input, max(0, $this->position - strlen($delimiter)), strlen($delimiter)) !== $delimiter) {
            throw $this->error('Literal string tidak ditutup');
        }

        $language = null;
        $datatype = null;

        if ($this->current() === '@') {
            $this->advance();
            $language = $this->readLanguageTag();
        } elseif ($this->current() === '^' && $this->peek() === '^') {
            $this->advance();
            $this->advance();
            $datatype = $this->current() === '<'
                ? $this->readIri()
                : $this->expandPrefixedName($this->readToken());
        }

        return [
            'value' => $value,
            'type' => 'literal',
            'datatype' => $datatype,
            'language' => $language,
        ];
    }

    private function parseBlankNodePropertyList(): string
    {
        $this->expect('[');
        $node = $this->newBlankNode();

        $this->skipWhitespaceAndComments();

        if ($this->current() === ']') {
            $this->advance();

            return $node;
        }

        $this->parsePredicateObjectList($node);

        $this->skipWhitespaceAndComments();
        $this->expect(']');

        return $node;
    }

    private function parseCollection(): string
    {
        $this->expect('(');
        $this->skipWhitespaceAndComments();

        if ($this->current() === ')') {
            $this->advance();

            return self::RDF_NIL;
        }

        $head = $this->newBlankNode();
        $current = $head;

        while (true) {
            $item = $this->parseObject();
            $this->addTriple($current, self::RDF_FIRST, $item);

            $this->skipWhitespaceAndComments();

            if ($this->current() === ')') {
                $this->advance();
                $this->addTriple($current, self::RDF_REST, [
                    'value' => self::RDF_NIL,
                    'type' => 'iri',
                    'datatype' => null,
                    'language' => null,
                ]);

                break;
            }

            $next = $this->newBlankNode();
            $this->addTriple($current, self::RDF_REST, [
                'value' => $next,
                'type' => 'bnode',
                'datatype' => null,
                'language' => null,
            ]);
            $current = $next;
        }

        return $head;
    }

    private function readIri(): string
    {
        $this->expect('<');
        $iri = '';

        while (! $this->eof()) {
            $char = $this->current();

            if ($char === '>') {
                $this->advance();

                return $this->resolveIri($iri);
            }

            if ($char === '\\') {
                $iri .= $this->readEscape();

                continue;
            }

            if ($char === "\n" || $char === "\r") {
                throw $this->error('IRI tidak boleh berisi baris baru');
            }

            $iri .= $this->advance();
        }

        throw $this->error('IRI tidak ditutup dengan ">"');
    }

    private function readNodeName(): string
    {
        $token = $this->readToken();

        if ($token === '') {
            throw $this->error('Nama node diharapkan');
        }

        if (str_starts_with($token, '_:')) {
            return $token;
        }

        return $this->expandPrefixedName($token);
    }

    private function readToken(): string
    {
        $token = '';

        while (! $this->eof()) {
            $char = $this->current();

            if (ctype_space($char) || in_array($char, [';', ',', '[', ']', '(', ')'], true)) {
                break;
            }

            if ($char === '.') {
                $next = $this->peek();

                if ($next === null || ctype_space($next) || in_array($next, ['#', ']', ')', ';', ','], true)) {
                    break;
                }
            }

            $token .= $this->advance();
        }

        return $token;
    }

    private function readWord(): string
    {
        $word = '';

        while (! $this->eof()) {
            $char = $this->current();

            if (ctype_space($char) || in_array($char, ['<', '"', "'", ';', ',', '[', ']', '(', ')', '.'], true)) {
                break;
            }

            $word .= $this->advance();
        }

        return $word;
    }

    private function readPrefixLabel(): string
    {
        $label = '';

        while (! $this->eof()) {
            $char = $this->current();

            if ($char === ':') {
                $this->advance();

                return $label;
            }

            if (ctype_space($char) || in_array($char, ['<', '[', ']', '(', ')', ';', ','], true)) {
                break;
            }

            $label .= $this->advance();
        }

        throw $this->error('Prefix harus diakhiri dengan ":"');
    }

    private function readLanguageTag(): string
    {
        $tag = '';

        while (! $this->eof()) {
            $char = $this->current();

            if (! ctype_alnum($char) && $char !== '-') {
                break;
            }

            $tag .= $this->advance();
        }

        return $tag;
    }

    private function readEscape(): string
    {
        $this->expect('\\');
        $char = $this->current();

        if ($char === null) {
            throw $this->error('Escape sequence tidak lengkap');
        }

        $this->advance();

        return match ($char) {
            't' => "\t",
            'b' => "\b",
            'n' => "\n",
            'r' => "\r",
            'f' => "\f",
            '"' => '"',
            "'" => "'",
            '\\' => '\\',
            'u' => $this->readUnicodeEscape(4),
            'U' => $this->readUnicodeEscape(8),
            default => $char,
        };
    }

    private function readUnicodeEscape(int $length): string
    {
        $hex = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $this->current();

            if ($char === null || ! ctype_xdigit($char)) {
                throw $this->error('Escape Unicode tidak valid');
            }

            $hex .= $this->advance();
        }

        $decoded = mb_chr((int) hexdec($hex), 'UTF-8');

        if ($decoded === false) {
            throw $this->error('Escape Unicode tidak dapat didekode');
        }

        return $decoded;
    }

    private function expandPrefixedName(string $token): string
    {
        $colon = strpos($token, ':');

        if ($colon === false) {
            throw $this->error("Nama '{$token}' bukan prefixed name yang valid");
        }

        $prefix = substr($token, 0, $colon);
        $local = substr($token, $colon + 1);

        if (! array_key_exists($prefix, $this->prefixes)) {
            throw $this->error("Prefix '{$prefix}' belum dideklarasikan");
        }

        return $this->prefixes[$prefix].$local;
    }

    private function resolveIri(string $iri): string
    {
        if ($this->base === null || preg_match('#^[a-z][a-z0-9+.-]*:#i', $iri) === 1) {
            return $iri;
        }

        return rtrim($this->base, '/').'/'.ltrim($iri, '/');
    }

    /**
     * @param  array{value: string, type: 'iri'|'literal'|'bnode', datatype: string|null, language: string|null}  $object
     */
    private function addTriple(string $subject, string $predicate, array $object): void
    {
        $this->triples[] = [
            'subject' => $subject,
            'predicate' => $predicate,
            'object' => $object['value'],
            'object_type' => $object['type'],
            'datatype' => $object['datatype'],
            'language' => $object['language'],
        ];
    }

    private function newBlankNode(): string
    {
        $this->blankNodeCounter++;

        return '_:b'.$this->blankNodeCounter;
    }

    private function skipWhitespaceAndComments(): void
    {
        while (! $this->eof()) {
            $char = $this->current();

            if (ctype_space($char)) {
                $this->advance();

                continue;
            }

            if ($char === '#') {
                while (! $this->eof() && $this->current() !== "\n") {
                    $this->advance();
                }

                continue;
            }

            break;
        }
    }

    private function startsWithKeyword(string $keyword): bool
    {
        $length = strlen($keyword);

        if (strtoupper(substr($this->input, $this->position, $length)) !== strtoupper($keyword)) {
            return false;
        }

        $next = $this->input[$this->position + $length] ?? null;

        return $next === null || ctype_space($next) || $next === '#';
    }

    private function eof(): bool
    {
        return $this->position >= $this->length;
    }

    private function current(): ?string
    {
        return $this->input[$this->position] ?? null;
    }

    private function peek(int $offset = 1): ?string
    {
        return $this->input[$this->position + $offset] ?? null;
    }

    private function advance(): string
    {
        $char = $this->input[$this->position] ?? '';
        $this->position++;

        return $char;
    }

    private function expect(string $char): void
    {
        if ($this->current() !== $char) {
            throw $this->error("Karakter '{$char}' diharapkan");
        }

        $this->advance();
    }

    private function normalize(string $turtle): string
    {
        if (str_starts_with($turtle, "\xEF\xBB\xBF")) {
            $turtle = substr($turtle, 3);
        }

        return str_replace(["\r\n", "\r"], "\n", $turtle);
    }

    private function error(string $message): RuntimeException
    {
        $before = substr($this->input, 0, $this->position);
        $line = substr_count($before, "\n") + 1;
        $lastNewline = strrpos($before, "\n");
        $column = $lastNewline === false ? $this->position + 1 : $this->position - $lastNewline;
        $lines = explode("\n", $this->input);
        $snippet = trim($lines[$line - 1] ?? '');

        if (strlen($snippet) > 180) {
            $snippet = substr($snippet, 0, 180).'...';
        }

        return new RuntimeException(
            "Turtle parse error baris {$line}, kolom {$column}: {$message}. Potongan: {$snippet}"
        );
    }
}
