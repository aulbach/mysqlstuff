<?php

/**
 * Mayflower-Tools Timeout Starter
 * (c) 2012 by Alex Aulbach, Mayflower GmbH
 *
 * php mt-timeout-starter COMMAND1 TIMEOUT COMMAND2
 *
 *
 * Opens new process as bash-command COMMAND1 (escape with ')
 * Then it lurks, until any input comes into
 * It will then start COMMAND2 and pipe everything from COMMAND1 to COMMAND2
 * until TIMEOUT time (seconds) noting comes from COMMAND1
 * Then it will kill COMMAND1, stops piping and reads the output of COMMAND2 and kill it too.
 * This is repeated until SIGINT.
 *
 * Example usage:
 * php mt-timeout-starter.php 'tcpdump -s 65535 -x -nn -q -tttt -i lo port 3306' 2 'pt-query-digest --type tcpdump --group-by fingerprint --order-by Query_time:sum --limit 1 --fingerprints --report-format query_report --explain u=root,p=root,D=vrnet_TESTING' | multitail -j
 *
 * This will start tcpdump on port 3306 and pipes the output to pt-query-digest (type tcpdump).
 * When there is 2 seconds no query, the input is finished. The result is piped into multitail.
 * This can be used to click on your website, make some queries, and when the script is stopped, the output is automatically generated.
 */



// shell-scrip, which should be started
$_reader = $argv[1];
// after receiving nothing kill reader after this time
$_delay = $argv[2];
// and start this proggy to pipe the readed stuff in
$_what = $argv[3];


echo "READER-CMD: $_reader\n";
echo "DELAY:      $_delay\n";
echo "START:      $_what\n";


///////////////////////////////////
// start reader


$descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
);


while (true) {

        $process = proc_open($_reader, $descriptors, $pipes);
        if (!is_resource($process)) {
                die("Could not open process\n");
        }


        $fp =& $pipes[1];


        // now start the second process


        // lurk, until something comes in
        // read 1 char

        echo "... lurking for input ...\n"; flush();

        $firstchar = fread($fp, 1);

        echo "... starting sub-process ...\n";
        $process2 = proc_open($_what, $descriptors, $pipes2);
        if (!is_resource($process2)) {
                die("Could not open what-process\n");
        }
        $fpwin =& $pipes2[0];
        $fpwout =& $pipes2[1];

        fwrite($fpwin, $firstchar);


        echo "... buffering: "; flush();

        stream_set_blocking($fp, 0);
        stream_set_timeout($fp, $_delay);

        $read = array($fp);
        $writ = NULL;
        $excp = NULL;

        // then read until timeout happens

        $cnt = 0;
        while (!feof($fp) && empty($status['timed_out'])) {
                $cnt += 1;
                if ($cnt%10 == 0) {
                        echo "."; flush();
                }

                $streams = stream_select($read, $writ, $excp, $_delay);

                if ($streams === false) {
                        die("ERROR: stream_select returns false\n");
                }

                if ($streams > 0) {
                        $line = stream_get_line($read[0], 1024);
                } else {
                        $line = '';
                }

                fwrite($fpwin, $line);
                if (empty($line)) {
                        break;
                }
                $status = socket_get_status($fp);

        }

        echo "\n... sending ...\n###################################################################\n"; flush();

        fclose($fpwin);

        proc_terminate($process);

        while (!feof($fpwout)) {
                echo fread($fpwout, 1024);
        }

        proc_terminate($process2);

        echo "... ready ...\n"; flush();
}

