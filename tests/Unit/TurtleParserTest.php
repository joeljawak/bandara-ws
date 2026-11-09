<?php

namespace Tests\Unit;

use App\Support\TurtleParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TurtleParserTest extends TestCase
{
    public function test_parser_membaca_prefix_tipe_literal_dan_angka(): void
    {
        $turtle = <<<'TTL'
        @prefix ex: <http://example.org/bandara#> .
        @prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
        @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

        ex:Bandung a ex:Airport ;
            rdfs:label "Bandung"@id ;
            ex:hasIATACode "BDO" ;
            ex:elevation 742 ;
            ex:latitude -6.9 ;
            ex:active true ;
            ex:year "2020"^^xsd:integer .
        TTL;

        $triples = (new TurtleParser)->parse($turtle);

        $this->assertCount(7, $triples);
        $this->assertSame('http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $triples[0]['predicate']);
        $this->assertSame('http://example.org/bandara#Airport', $triples[0]['object']);

        $label = $this->findTriple($triples, 'label');
        $this->assertSame('Bandung', $label['object']);
        $this->assertSame('id', $label['language']);

        $elevation = $this->findTriple($triples, 'elevation');
        $this->assertSame('literal', $elevation['object_type']);
        $this->assertSame('742', $elevation['object']);

        $year = $this->findTriple($triples, 'year');
        $this->assertSame('http://www.w3.org/2001/XMLSchema#integer', $year['datatype']);
    }

    public function test_parser_membaca_blank_node_dan_koleksi(): void
    {
        $turtle = <<<'TTL'
        @prefix ex: <http://example.org/bandara#> .

        ex:airport_XXX ex:tags [ ex:name "utama" ], ( "a" "b" ) .
        TTL;

        $triples = (new TurtleParser)->parse($turtle);

        $this->assertNotEmpty($triples);

        $nameCount = 0;
        $firstCount = 0;

        foreach ($triples as $triple) {
            if ($triple['predicate'] === 'http://example.org/bandara#name') {
                $nameCount++;
            }

            if ($triple['predicate'] === TurtleParser::RDF_FIRST) {
                $firstCount++;
            }
        }

        $this->assertSame(1, $nameCount);
        $this->assertSame(2, $firstCount);
    }

    public function test_parser_menolak_file_yang_tidak_ada(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TurtleParser)->parseFile('file-yang-tidak-ada.ttl');
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: string, object_type: string, datatype: string|null, language: string|null}>  $triples
     * @return array{subject: string, predicate: string, object: string, object_type: string, datatype: string|null, language: string|null}
     */
    private function findTriple(array $triples, string $predicate): array
    {
        foreach ($triples as $triple) {
            if (str_ends_with($triple['predicate'], '#'.$predicate)) {
                return $triple;
            }
        }

        $this->fail("Predikat {$predicate} tidak ditemukan.");
    }
}
