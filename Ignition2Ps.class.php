<?php

class Ignition2Ps
{
    protected $hand;

    public function __construct($ignition_hh_dir)
    {
        $this->ignition_hh_dir = $ignition_hh_dir;
    }

    /**
     * Process entire Ignition hand history directory
     */
    public function processIgnitionHh()
    {
        debug("%s", $this->ignition_hh_dir);

        if (! is_dir($this->ignition_hh_dir) || ! is_readable($this->ignition_hh_dir))
            throw new Exception($this->ignition_hh_dir . " does not exist or is not a directory");

        if ($dh = opendir($this->ignition_hh_dir)) {
            while (($account_dir = readdir($dh)) !== false) {
                if (! is_dir($this->ignition_hh_dir . '/' . $account_dir))
                    continue;

                if (! preg_match('/^\d+$/', $account_dir))
                    continue;

                $this->processAccountDir($account_dir);
            }

            closedir($dh);
        }
    }

    /**
     * Process Ignition hand history account directory
     */
    protected function processAccountDir($account_dir)
    {
        debug("    %s", $account_dir);

        $this->account_dir = $account_dir;

        if ($dh = opendir($this->ignition_hh_dir . '/' . $account_dir)) {
            while (($hh_file = readdir($dh)) !== false) {
                $full_path = $this->ignition_hh_dir . '/' . $account_dir . '/' . $hh_file;

                if (! is_file($full_path) || ! is_readable($full_path))
                    continue;

                if (! preg_match('/^\d+$/', $account_dir))
                    continue;

                $this->processFile($hh_file);
            }

            closedir($dh);
        }
    }

    /**
     * Process individual Ignition hand history file
     */
    protected function processFile($hh_file)
    {
        debug("        %s", $hh_file);

        $this->hh_file = $hh_file;

        $file = new IgnitionHhFile($hh_file, $this);

        try {
            if (! $file->open()) // Not an Ignition HH file, ignore
                return;

            while ($out = $file->convertNextHand()) {
                echo $out;
            }
        } catch (IgnitionHhFileException $e) {
            throw new Exception(sprintf("%s in file %s on line %d: %s", $e->getMessage(), $file->file['full_path'], $file->lineno, $file->line));
        } catch (Ignition2PsHandException $e) {
            throw new Exception(sprintf("%s in file %s on line %d", $e->getMessage(), $file->file['full_path'], $file->handlineno));
        }
    }
}

