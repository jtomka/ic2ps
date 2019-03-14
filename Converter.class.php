<?php

final class Converter extends Base
{
    static public function run(HhReaderInterface $reader, HhWriterInterface $writer)
    {
        foreach ($reader->getAccounts() as $account) {
            $hands = $reader->parseAccountHandHistory($account);
            $writer->generateHandHistory($hands);
        }
    }
}

