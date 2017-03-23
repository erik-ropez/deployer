<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Console;

use Deployer\Deployer;
use Deployer\Exception\GracefulShutdownException;
use Deployer\Exception\NonFatalException;
use Deployer\Host\Host;
use Deployer\Task\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends Command
{
    /**
     * @var Deployer
     */
    private $deployer;

    /**
     * @var Host
     */
    private $host;

    /**
     * @param Deployer $deployer
     */
    public function __construct(Deployer $deployer)
    {
        parent::__construct('worker');
        $this->setDescription('Deployer uses workers for parallel deployment');
        if (method_exists($this, 'setHidden')) {
            $this->setHidden(true);
        }
        $this->deployer = $deployer;
        $this->addOption(
            'hostname',
            null,
            InputOption::VALUE_REQUIRED
        );
        $this->addOption(
            'task',
            null,
            InputOption::VALUE_REQUIRED
        );
        $this->addOption(
            'log',
            null,
            InputOption::VALUE_REQUIRED
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->doExecute($input, $output);
        } catch (GracefulShutdownException $e) {
            $this->deployer->informer->taskException($e, $this->host);
            return 1;
        } catch (NonFatalException $e) {
            $this->deployer->informer->taskException($e, $this->host);
            return 2;
        } catch (\Throwable $e) {
            $this->deployer->informer->taskException($e, $this->host);
            return 255;
        }
    }


    private function doExecute(InputInterface $input, OutputInterface $output)
    {
        $hostname = $input->getOption('hostname');
        $this->host = $host = $this->deployer->hosts->get($hostname);

        $task = $input->getOption('task');
        $task = $this->deployer->tasks->get($task);

        if (!empty($input->getOption('log'))) {
            $this->deployer->config['log_file'] = $input->getOption('log');
        }

        $informer = $this->deployer->informer;

        if ($task->shouldBePerformed($host)) {
            $task->run(new Context($host, $input, $output));
            $informer->endOnHost($hostname);
        }
    }
}
