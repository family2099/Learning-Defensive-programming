<?php

date_default_timezone_set("Asia/Taipei");

/**
 * A PHP daemon demo file.
 *
 * @author     Asika
 * @email      asika@asikart.com
 * @date       2013-10-12
 *
 * @copyright  Copyright (C) 2013 - Asika.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Daemon Application.
 */
class DaemonSignalListener
{
    /**
     * Store process id for close self.
     *
     * @var int
     */
    protected $processId;

    /**
     * File path to save pid.
     *
     * @var string
     */
    protected $pidFile = 'daemon.pid';

    /**
     * Daemon log file.
     *
     * @var string
     */
    protected $logFile = 'phpdeamon.log';

    /**
     * Create a daemon process.
     *
     * @return  DaemonHttpApplication  Return self to support chaining.
     *
     *
     * 當主程序啟動後，我們運用 pcntl_fork() 來複製一份自己，fork 之後會返回一個 pid 值。
     * 此時父程序與子程序都同樣由這一行繼續跑下去，父程序會取得子程序的 pid，而子程序則返回 0 。
     * 因此父程序會進到 if block 內，並且終止自己，這是第一次脫殼。
     */
    public function execute()
    {
        //PHP 中建一個子程序，要叫用 pcntl_fork()函式
        // Create first child.
        if(pcntl_fork())
        {
            // I'm the parent
            // Protect against Zombie children
            ////等待子进程中断，防止子进程成为僵尸进程
            pcntl_wait($status);
            exit;
        }

        // Make first child as session leader.
        //將這個子程序設為 Session Leader，讓 terminal 對我們的程序保有控制權
        posix_setsid();

        // Create second child.
        $pid = pcntl_fork();

        if ($pid == -1) {
            //错误处理：创建子进程失败时返回-1.
             die('could not fork');
        }

        if($pid)
        {
            // If pid not 0, means this process is parent, close it.
            $this->processId = $pid;
            $this->storeProcessId();

            exit;
        }

        //間
        $this->addLog('Daemonized');

        fwrite(STDOUT, "Daemon Start\n-----------------------------------------\n");

        $this->registerSignalHandler();

        // Declare ticks to start signal monitoring. When you declare ticks, PCNTL will monitor
        // incoming signals after each tick and call the relevant signal handler automatically.
        declare (ticks = 1);

        while(true)
        {
            $this->doExecute();
        }

        return $this;
    }

    /**
     * Method to run the application routines.
     * Most likely you will want to fetch a queue to do something.
     *
     * @return  void
     */
    protected function doExecute()
    {
        // Do some stuff you want.
    }

    /**
     * Method to attach signal handler to the known signals.
     *
     * @return  void
     */
    protected function registerSignalHandler()
    {
        $this->addLog('registerHendler');

        pcntl_signal(SIGINT, array($this, 'shutdown'));

        pcntl_signal(SIGTERM, array($this, 'shutdown'));

        pcntl_signal(SIGUSR1, array($this, 'customSignal'));
    }

    /**
     * Store the pid to file.
     *
     * @return  DaemonSignalListener  Return self to support chaining.
     */
    protected function storeProcessId()
    {
        $file = $this->pidFile;

        // Make sure that the folder where we are writing the process id file exists.
        $folder = dirname($file);

        if(!is_dir($folder))
        {
            mkdir($folder);
        }

        file_put_contents($file, $this->processId);

        return $this;
    }

    /**
     * Shut down our daemon.
     *
     * @param   integer  $signal  The received POSIX signal.
     */
    public function shutdown($signal)
    {
        $this->addLog('Shutdown by signal: ' . $signal);

        $pid = file_get_contents($this->pidFile);

        // Remove the process id file.
        @ unlink($this->pidFile);

        passthru('kill -9 ' . $pid);
        exit;
    }

    /**
     * Hendle the SIGUSR1 signal.
     *
     * @param   integer  $signal  The received POSIX signal.
     */
    public function customSignal($signal)
    {
        $this->addLog('Execute custom signal: ' . $signal);
    }

    /**
     * Add a log to log file.
     *
     * @param   string  $text  Log string.
     */
    protected function addLog($text)
    {
        $file = $this->logFile;

        $time = new Datetime();

        $text = sprintf("%s - %s\n", $text, $time->format('Y-m-d H:i:s'));

        $fp = fopen($file, 'a+');
        fwrite($fp,$text);
        fclose($fp);
    }
}



// Start Daemon
// --------------------------------------------------
$daemon = new DaemonSignalListener();

$daemon->execute();