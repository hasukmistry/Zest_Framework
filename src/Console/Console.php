<?php

/**
 * This file is part of the alphaz Framework.
 *
 * @author Muhammad Umer Farooq (Malik) <mumerfarooqlablnet01@gmail.com>
 *
 * @link https://github.com/alphazframework/framework
 *
 * @author Muhammad Umer Farooq <lablnet01@gmail.com>
 * @author-profile https://www.facebook.com/Muhammadumerfarooq01/
 *
 * For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 *
 * @license MIT
 */

namespace alphaz\Console;

use alphaz\Console\Commands as InternalCommands;
use alphaz\Container\Container;
use alphaz\Data\Arrays;

class Console
{
    /**
     * Instance of container.
     *
     * @since 1.0.0
     *
     * @var \alphaz\Container\Container
     */
    private $container;

    /**
     * Commanads.
     *
     * @since 1.0.0
     *
     * @var array
     */
    private $commands = [];

    /**
     * Create a new console instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->container = new Container();
        $internalCommands = (new InternalCommands())->getCommands();
        $externalCommands = [];
        if (class_exists("\Config\Commands")) {
            $externalCommands = (new \Config\Commands())->getCommands();
        }

        $this->commands = array_merge($internalCommands, $externalCommands);
    }

    /**
     * Parse the flags from command.
     *
     * @param array $flags Raw flags.
     *
     * @return array
     */
    public function parseFlags($flags): array
    {
        $params = [];
        $f = explode(',', $flags);
        if (Arrays::isReallyArray($f)) {
            foreach ($f as $flag => $fs) {
                $param = explode('=', $fs);
                if (isset($param[1])) {
                    $params[$param[0]] = $param[1];
                }
            }
        } else {
            $param = explode('=', $flags);
            if (isset($param[1])) {
                $params[$param[0]] = $param[1];
            }
        }

        return $params;
    }

    /**
     * Get all commands.
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Run the alphaz console.
     *
     * @return void
     */
    public function run($param): void
    {

        // registering the commands to container.
        foreach ($this->commands as $command) {
            $this->container->register([$command[1], $command[0]], new $command[1]());
        }

        $sign = isset($param[1]) ? $param[1] : 'list';
        $output = new Output();
        $input = new Input();
        if ($this->container->has($sign)) {
            $cmd = $this->container->get($sign);

            // default.
            if (!isset($param[2])) {
                if (count($cmd->getFlags()) > 0) {
                    $output->error('You must provide the flags');
                    $output->error('For Help, php alphaz '.$cmd->getSign().' -h');
                    exit;
                }
                $cmd->handle($output, $input);
            }
            // flag for quite
            if (isset($param[2]) && strtolower($param[2]) == '-q') {
                $cmd->handle($output->quiet(), $input);
            }
            if (isset($param[2]) && isset($param[3]) && strtolower($param[2]) == '-p') {
                $params = $this->parseFlags($param[3]);
                $command_flags = $cmd->getFlags();
                // get keys from $params.
                $keys = array_keys($params);

                // check if the keys are in the command flags (check if extra flag passed).
                foreach ($keys as $key => $value) {
                    if (!in_array($value, $command_flags)) {
                        $output->error("Invalid flag: $value");
                        exit;
                    }
                }

                // check the keys should be in command flags.
                foreach ($command_flags as $command_flag) {
                    if (!in_array($command_flag, $keys)) {
                        $output->error("Missing flag: $command_flag");
                        exit;
                    }
                }

                $cmd->handle($output, $input, $params);
            }

            // flag for help
            if (isset($param[2]) && strtolower($param[2]) == '-h') {
                $args = $cmd->getFlags();
                $output->write('<yellow>Description:</yellow>', true);
                $output->write("<blue>\t".$cmd->getDescription().'</blue>', true);
                $output->write("\n<yellow>Usage:</yellow>", true);
                $output->write("<blue>\t".$cmd->getSign().'</blue>', true);
                if (count($args) > 0) {
                    $output->write("\n<yellow>Arguments:</yellow>", true);
                    $output->write("<blue>\t".implode(',', $args).'</blue>', true);
                }
                $output->write("\n<yellow>Options:</yellow>", true);
                $output->write('<green>-h, --help</green>');
                $output->write("<blue>\tDisplay this help message</blue>", true);
                $output->write('<green>--q, --quiet</green>');
                $output->write("<blue>\tDo not output any message</blue>", true);
            }
        } else {
            $output->error("Sorry, the given command ${sign} not found")->exit();
        }
    }
}
