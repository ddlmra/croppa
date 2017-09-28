<?php namespace Bkwld\Croppa\Commands;

// Deps
use Bkwld\Croppa\Storage;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * List ALL crops from config file
 */
class Styles extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'croppa:styles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage crops styles';
    /**
     * @var array
     */
    private $config;

    /**
     * Dependency inject
     *
     * @param Storage $storage
     */
    public function __construct($config = []) {
        parent::__construct();
        $this->config = $config;
    }

    /**
     * Execute the console command for laravel <=5.4
     *
     * @return void
     */
    public function fire() {
        $this->handle();
    }

    /**
     * Execute the console command for laravel >=5.5
     *
     * @return void
     */
    public function handle() {
            foreach ($this->config['styles'] as $name => $style) {
                $config = '(' . implode(', ', array_keys($style)) . ')';
                $this->output->writeLn("<info>$name</info> <comment>$config</comment>");
            }
    }

    /**
     * Get the console command options
     *
     * @return array;
     */
    protected function getOptions() {
        return [
            ['list', null, InputOption::VALUE_NONE, 'List all styles', null],
            ['test', null, InputOption::VALUE_NONE, 'Only return the crops that would be deleted'],
        ];
    }

}
