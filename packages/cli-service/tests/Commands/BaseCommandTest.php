<?php

namespace Tests\Commands;

use Tests\Mocks\TestCommand;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class BaseCommandTest extends TestCase
{
    /** @var ContainerInterface|PHPUnit\Framework\MockObject\MockObject */
    protected $container;

    /** @var TestCommand */
    protected $command;

    protected $input;

    protected $output;

    protected function setUp(): void
    {
        $this->container = $this->createMock(\DI\Container::class);

        $this->command = new TestCommand($this->container);

        $this->input = new ArrayInput([]);
        $this->output = new BufferedOutput();

        $this->command->run($this->input, $this->output);
    }

    public function testSuccessOutput()
    {
        $this->command->success('This is a success message.');

        $this->assertStringContainsString('This is a success message.', $this->output->fetch());
    }

    public function testErrorOutput(): void
    {
        // Simulate an error message
        $this->command->error('This is an error message.');

        // Fetch output from BufferedOutput
        $outputContent = $this->output->fetch();

        // Assert that the error message is in the output
        $this->assertStringContainsString('This is an error message.', $outputContent);
    }

    public function testInfoOutput(): void
    {
        // Simulate an informational message
        $this->command->info('This is an info message.');

        // Fetch output from BufferedOutput
        $outputContent = $this->output->fetch();

        // Assert that the info message is in the output
        $this->assertStringContainsString('This is an info message.', $outputContent);
    }

    public function testWarningOutput(): void
    {
        // Simulate a warning message
        $this->command->warning('This is a warning message.');

        // Fetch output from BufferedOutput
        $outputContent = $this->output->fetch();

        // Assert that the warning message is in the output
        $this->assertStringContainsString('This is a warning message.', $outputContent);
    }

    public function testTextOutput(): void
    {
        // Simulate a plain text message
        $this->command->text('This is a plain text message.');

        // Fetch output from BufferedOutput
        $outputContent = $this->output->fetch();

        // Assert that the plain text message is in the output
        $this->assertStringContainsString('This is a plain text message.', $outputContent);
    }

    public function testExecute()
    {
        $exitCode = $this->command->run($this->input, $this->output);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Test command executed successfully.', $this->output->fetch());
    }

    // public function testGetService()
    // {
    //     // TODO: Implement testGetService() method
    // }
}