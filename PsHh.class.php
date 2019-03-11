<?php

class PsHh {
    protected $ps_hh_dir;
    protected $hand_store;

    public function __construct($ps_hh_dir)
    {
        $this->ps_hh_dir = $ps_hh_dir;
        $this->hand_store = array();
    }

    public function store(Ignition2PsHand $hand)
    {
        // HH20170822 Riceia - $0.01-$0.02 - USD No Limit Hold'em.txt
        $ps_filename = sprintf("HH%s %s - $%.02f-$%.02f - USD %s %s.txt", strftime("%Y%m%d", $hand->getTimestamp()), $hand->getPsTableName(), $hand->getSb(), $hand->getBb(), $hand->getPsLimit(), $hand->getPsGame());

        $this->hand_store[$hand->getPsAccountId()][$ps_filename][$hand->getTimestamp()] = $hand->getPsFormat();
    }

    public function process()
    {
        ksort($this->hand_store, SORT_STRING);
        foreach ($this->hand_store as $ps_account_id => $filenames) {
            $dirname = sprintf("%s/%s", $this->ps_hh_dir, $ps_account_id);

            if (! mkdir($dirname, 0755, true))
                continue;

            ksort($filenames, SORT_STRING);
            foreach ($filenames as $filename => $timestamps) {

                debug("%s/%s/%s (%d)", $this->ps_hh_dir, $ps_account_id, $filename, count($timestamps));

                if (! $fh = fopen($dirname . '/' . $filename))
                    continue;

                ksort($timestamps, SORT_NUMERIC);
                foreach ($timestamps as $ps_format_hand) {
                    echo $ps_format_hand;
                }

                fclose($fh);
            }
        }
    }
}

