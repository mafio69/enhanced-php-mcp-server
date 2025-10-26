<?php

namespace App\Commands;

use App\Services\ServerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddServerCommand extends Command
{
    protected static $defaultName = 'app:add-server';

    private ServerService $serverService;

    public function __construct(ServerService $serverService)
    {
        $this->serverService = $serverService;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Adds a new server.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the server.')
            ->addArgument('ipAddress', InputArgument::REQUIRED, 'The IP address of the server.')
            ->addArgument('port', InputArgument::REQUIRED, 'The port of the server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $ipAddress = $input->getArgument('ipAddress');
        $port = (int)$input->getArgument('port');

        $serverData = [
            'name' => $name,
            'ipAddress' => $ipAddress,
            'port' => $port,
        ];

        $this->serverService->addServer($serverData);

        $output->writeln('Server added successfully.');

        return Command::SUCCESS;
    }
}
