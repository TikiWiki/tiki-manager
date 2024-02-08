<?php

namespace TikiManager\Hooks;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use TikiManager\Application\Instance;
use TikiManager\Application\Version;
use TikiManager\Config\App;

class TikiCommandHook implements HookInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var string The hook machine name
     */
    protected $name;

    /**
     * @var array
     */
    protected $preHookVars = [];

    /**
     * @var array
     */
    protected $postHookVars = [];

    /**
     * @var array
     */
    protected $instanceIds = [];

    /**
     * @param string $name
     * @param LoggerInterface $logger
     */
    public function __construct(string $name, LoggerInterface $logger)
    {
        $this->name = $name;
        $this->logger = $logger;
    }

    /**
     * Execute hooks scripts files
     * @param $type
     */
    public function execute($type): void
    {
        $scripts = $this->getScripts($type);

        if (empty($scripts)) {
            $this->logger->debug('No ' . $type . ' hook scripts were found in ' . $this->getPath($type));
            return;
        }

        $this->logger->info('Executing ' . $type . ' hooks');

        $vars = $type . 'HookVars';
        $env = array_merge($_ENV, $this->$vars);

        foreach ($scripts as $script) {
            $this->logger->info('Hook file: ' . $script->getPathname());

            $command = $this->buildScriptCommand($script->getRelativePathname(), $script->getPath(), $env);
            $success = $this->runScriptCommand($command);

            $this->logger->info('Hook script {status}', ['status' => $success ? 'succeeded' : 'failed']);
        }
    }

    public function getPath(string $type = ''): string
    {
        $path = App::get('HookHandler')->getHooksFolder() . DS . $this->getHookName();

        return $type ? $path . DS . $type : $path;
    }

    /**
     * @param string $type
     * @return Finder|null
     */
    public function getScripts(string $type): ?Finder
    {
        $folder = $this->getPath($type);

        try {
            $finder = new Finder();
            return $finder
                ->in($folder)
                ->files()
                ->name('*.sh');
        } catch (DirectoryNotFoundException $e) {
            $this->logger->debug($e->getMessage());
            return null;
        }
    }

    protected function buildScriptCommand(string $file, string $cwd = null, array $env = []): Process
    {
        return new Process(['bash', $file], $cwd, $env);
    }

    protected function runScriptCommand(Process $process): bool
    {
        $this->logger->debug(
            'Command {command}',
            [
                'command' => $process->getCommandLine(),
                'cwd' => $process->getWorkingDirectory(),
                'env' => $process->getEnv()
            ]
        );

        $process->run();

        if (!$success = $process->getExitCode()  === 0) {
            $this->logger->error($process->getErrorOutput());
        }

        $this->logger->debug("Command output: \n" . trim($process->getOutput() ?? ''));

        return $success;
    }

    /**
     * @return string
     */
    public function getHookName(): string
    {
        return $this->name;
    }

    public function registerPreHookVars()
    {
    }

    public function registerPostHookVars(array $vars)
    {
        $instance = $vars['instance'] ?? null;

        if ($instance instanceof Instance) {
            $this->registerPostInstanceVars($instance);
        }
    }

    protected function registerPostInstanceVars(Instance $instance)
    {
        $instanceId = $instance->id;
        $this->instanceIds[] = $instanceId;
        $this->postHookVars['INSTANCE_IDS'] = implode(',', $this->instanceIds);
        $this->postHookVars['INSTANCE_TYPE_' . $instanceId] = $instance->type;
        $this->postHookVars['INSTANCE_VCS_TYPE_' . $instanceId] = $instance->vcs_type;
        $this->postHookVars['INSTANCE_NAME_' . $instanceId] = $instance->name;
        $this->postHookVars['INSTANCE_WEBROOT_' . $instanceId] = $instance->webroot;
        $this->postHookVars['INSTANCE_WEBURL_' . $instanceId] = $instance->weburl;
        $this->postHookVars['INSTANCE_TEMPDIR_' . $instanceId] = $instance->tempdir;
        $this->postHookVars['INSTANCE_PHPEXEC_' . $instanceId] = $instance->phpexec;
        $this->postHookVars['INSTANCE_PHPVERSION_' . $instanceId] = $instance->phpversion;
        $this->postHookVars['INSTANCE_BACKUP_USER_' . $instanceId] = $instance->backup_user;
        $this->postHookVars['INSTANCE_BACKUP_GROUP_' . $instanceId] = $instance->backup_group;
        $this->postHookVars['INSTANCE_BACKUP_PERM_' . $instanceId] = $instance->backup_perm;

        $latestVersion = $instance->getLatestVersion();
        $this->postHookVars['INSTANCE_BRANCH_' . $instanceId] = $latestVersion instanceof Version ? $latestVersion->branch : null ;
        $this->postHookVars['INSTANCE_LAST_ACTION_' . $instanceId] = $instance->last_action;
        $this->postHookVars['INSTANCE_LAST_ACTION_DATE_' . $instanceId] = $instance->last_action_date;
    }

    public function getPostHookVars(): array
    {
        return $this->postHookVars;
    }
}
