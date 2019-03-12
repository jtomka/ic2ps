<?php

class Ic2Ps
{
    const PS_ACCOUNT_PREFIX = 'Ignition';

    const PS_TABLE_PREFIX = 'Ignition';

    const PS_HAND_PREFIX = '99999';

    // Ignition hand history directory (full path)
    protected $ic_hh_dir;

    // Ignition hand history account directory (dirname)
    protected $ic_account_dir;

    // Current Ignition hand history file (basename)
    protected $ic_hh_filename;

    // PokerStars hand history directory (full path)
    protected $ps_hh_dir;

    // PokerStars hand history writer object
    protected $ps_hh;

    public function __construct($ic_hh_dir, $ps_hh_dir)
    {
        $this->ic_hh_dir = $ic_hh_dir;
        $this->ps_hh_dir = $ps_hh_dir;

        $this->ps_hh = new PsHh($ps_hh_dir);
    }

    public function getIcHhDir()
    {
        return $this->ic_hh_dir;
    }

    public function getIcAccountDir()
    {
        return $this->ic_account_dir;
    }

    public function getIcAccountId()
    {
        return $this->getIcAccountDir();
    }

    public function getIcHhFilename()
    {
        return $this->ic_hh_filename;
    }

    public function getPsHandId($hand_id)
    {
        return self::PS_HAND_PREFIX . $hand_id;
    }

    /**
     * Table name to use for generated PokerStars hand history
     */
    public function getPsTableName($table)
    {
        return self::PS_TABLE_PREFIX . $table;
    }

    /**
     * Account ID (player name) to use for generated PokerStars hand history
     */
    public function getPsAccountId()
    {
        return self::PS_ACCOUNT_PREFIX . $this->getIcAccountId();
    }

    /**
     * Process entire Ignition hand history directory
     */
    public function processHh()
    {
        debug("Ignition Hand History: %s", $this->ic_hh_dir);

        if (! is_dir($this->ic_hh_dir) || ! is_readable($this->ic_hh_dir))
            throw new Exception($this->ic_hh_dir . " does not exist or is not a directory");

        if ($dh = opendir($this->ic_hh_dir)) {
            while (($ic_account_dir = readdir($dh)) !== false) {
                if (! is_dir($this->ic_hh_dir . '/' . $ic_account_dir))
                    continue;

                if (! preg_match('/^\d+$/', $ic_account_dir))
                    continue;

                $this->processAccountDir($ic_account_dir);
            }

            closedir($dh);
        }

        // Save all generated PokerStars hands into hand history files
        $this->ps_hh->process();
    }

    /**
     * Process Ignition hand history account directory
     */
    protected function processAccountDir($ic_account_dir)
    {
        debug("    Account: %s", $ic_account_dir);

        $this->ic_account_dir = $ic_account_dir;

        if ($dh = opendir($this->ic_hh_dir . '/' . $ic_account_dir)) {
            while (($ic_hh_filename = readdir($dh)) !== false) {
                $ic_full_path = $this->ic_hh_dir . '/' . $ic_account_dir . '/' . $ic_hh_filename;

                if (! is_file($ic_full_path) || ! is_readable($ic_full_path))
                    continue;

                if (! preg_match('/^\d+$/', $ic_account_dir))
                    continue;

                $this->processFile($ic_hh_filename);
            }

            closedir($dh);
        }
    }

    /**
     * Process individual Ignition hand history file
     */
    protected function processFile($ic_hh_filename)
    {
        deb("        %s", $ic_hh_filename);

        $this->ic_hh_filename = $ic_hh_filename;

        $ic_file = new IcFile($this);

        try {
            if (! $ic_file->open()) { 
                // Not an Ignition HH file, ignore
                return;
            }

            $i = 1;
            while ($hand = $ic_file->convertNextHand()) {
                $this->ps_hh->store($hand);
                $i++;
            }
            debug(" (%d hands)", $i);
        } catch (IcFileException $e) {
            throw new Exception(sprintf("%s in file %s on line %d: %s", $e->getMessage(), $ic_file->getFullPath(), $ic_file->getLineNo(), $ic_file->getLine()));

        } catch (HandException $e) {
            throw new Exception(sprintf("%s in file %s on line %d", $e->getMessage(), $ic_file->getFullPath(), $ic_file->getHandLineNo()));

        } catch (PsHhException $e) {
            throw new Exception(sprintf("%s", $e->getMessage()));
        }
    }
}

