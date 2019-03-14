<?php

interface HhWriterInterface extends HhInterface
{
    public function generateHandHistory(Hand[] $hands);
}

