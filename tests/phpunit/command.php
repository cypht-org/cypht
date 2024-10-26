<?php

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class Hm_Test_Command extends TestCase
{
    /** @var ContainerInterface*/
    protected $container;

    /** @var TestCommand */
    protected $command;

    protected $input;

    protected $output;

    protected function setUp(): void
    {
        require_once __DIR__.'/services/mocks.php';
        $this->container = $this->getMockBuilder(ContainerInterface::class)
        ->disableOriginalConstructor()
        ->getMock();

        $this->command = new Hm_TestCommand($this->container);

        $this->input = new ArrayInput([]);
        $this->output = new BufferedOutput();

        $this->command->run($this->input, $this->output);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_success_output()
    {
        $this->command->success('This is a success message.');

        $this->assertStringContainsString('This is a success message.', $this->output->fetch());
    }

    public function test_error_output(): void
    {
        // Simulate an error message
        $this->command->error('This is an error message.');

        // Fetch output from BufferedOutput
        $outputContent = $this->output->fetch();

        // Assert that the error message is in the output
        $this->assertStringContainsString('This is an error message.', $outputContent);
    }

    public function test_info_output(): void
    {
        // Simulate an informational message
        $this->command->info('This is an info message.');

        // Fetch output from BufferedOutput
        $outputContent = $this->output->fetch();

        // Assert that the info message is in the output
        $this->assertStringContainsString('This is an info message.', $outputContent);
    }

    public function test_warning_output(): void
    {
        // Simulate a warning message
        $this->command->warning('This is a warning message.');

        // Fetch output from BufferedOutput
        $outputContent = $this->output->fetch();

        // Assert that the warning message is in the output
        $this->assertStringContainsString('This is a warning message.', $outputContent);
    }

    public function test_text_output(): void
    {
        // Simulate a plain text message
        $this->command->text('This is a plain text message.');

        // Fetch output from BufferedOutput
        $outputContent = $this->output->fetch();

        // Assert that the plain text message is in the output
        $this->assertStringContainsString('This is a plain text message.', $outputContent);
    }

    public function test_execute()
    {
        $exitCode = $this->command->run($this->input, $this->output);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Test command executed successfully.', $this->output->fetch());
    }

    // public function test_get_service()
    // {
    //     // TODO: Implement testGetService() method
    // }
}