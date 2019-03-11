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
        debug("PokerStars Hand History: %s", $this->ps_hh_dir);

        ksort($this->hand_store, SORT_STRING);
        foreach ($this->hand_store as $ps_account_id => $filenames) {
            debug("    Account: %s", $ps_account_id);

            $dirname = sprintf("%s/%s", $this->ps_hh_dir, $ps_account_id);

            if (file_exists($dirname)) {
                if (! is_dir($dirname) || ! is_writeable($dirname))
                    throw new PsHhException(sprintf("%s is not directory or is not writeable", $dirname));
            } else {
                if (! @mkdir($dirname, 0755, true))
                    throw new PsHhException(sprintf("%s can't be created", $dirname));
            }

            ksort($filenames, SORT_STRING);
            foreach ($filenames as $filename => $timestamps) {
                debug("        %s (%d hands)", $filename, count($timestamps));

                $full_name = $dirname . '/' . $filename;

                if (! $fh = @fopen($full_name, 'w'))
                    throw new PsHhException(sprintf("%s can't be created", $dirname));

                ksort($timestamps, SORT_NUMERIC);
                try {
                    foreach ($timestamps as $ps_format_hand) {
                        if (! fputs($fh, $ps_format_hand))
                            throw new PsHhException(sprintf("%s failed to write to file", $full_name));
                    }
                } catch (PsHhException $e) {
                    throw $e;
                } finally {
                    fclose($fh);
                }
            }
        }
    }
}

