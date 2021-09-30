<?php

declare(strict_types=1);

namespace Sts\KafkaBundle\Client\Consumer;

use RdKafka\KafkaConsumer as RdKafkaConsumer;
use RdKafka\Message as RdKafkaMessage;
use Sts\KafkaBundle\Client\Contract\ConsumerInterface;
use Sts\KafkaBundle\Configuration\ConfigurationResolver;
use Sts\KafkaBundle\Configuration\Type\EnableAutoCommit;
use Sts\KafkaBundle\Configuration\Type\MaxRetries;
use Sts\KafkaBundle\Configuration\Type\MaxRetryDelay;
use Sts\KafkaBundle\Configuration\Type\RetryDelay;
use Sts\KafkaBundle\Configuration\Type\RetryMultiplier;
use Sts\KafkaBundle\Configuration\Type\Timeout;
use Sts\KafkaBundle\Configuration\Type\Topics;
use Sts\KafkaBundle\Event\PostMessageConsumedEvent;
use Sts\KafkaBundle\Event\PreMessageConsumedEvent;
use Sts\KafkaBundle\Exception\NullMessageException;
use Sts\KafkaBundle\Exception\RecoverableMessageException;
use Sts\KafkaBundle\Exception\ValidationException;
use Sts\KafkaBundle\Factory\MessageFactory;
use Sts\KafkaBundle\RdKafka\Context;
use Sts\KafkaBundle\RdKafka\KafkaConfigurationFactory;
use Sts\KafkaBundle\Traits\CheckForRdKafkaExtensionTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ConsumerClient
{
    use CheckForRdKafkaExtensionTrait;

    private KafkaConfigurationFactory $kafkaConfigurationFactory;
    private MessageFactory $messageFactory;
    private ConfigurationResolver $configurationResolver;
    private ?EventDispatcherInterface $dispatcher;

    private int $consumedMessages = 0;
    private float $consumptionTimeMs = 0;

    public function __construct(
        KafkaConfigurationFactory $kafkaConfigurationFactory,
        MessageFactory $messageFactory,
        ConfigurationResolver $configurationResolver,
        ?EventDispatcherInterface $dispatcher
    ) {
        $this->kafkaConfigurationFactory = $kafkaConfigurationFactory;
        $this->messageFactory = $messageFactory;
        $this->configurationResolver = $configurationResolver;
        $this->dispatcher = $dispatcher;
    }

    public function consume(ConsumerInterface $consumer, ?InputInterface $input = null): bool
    {
        $this->isKafkaExtensionLoaded();

        $configuration = $this->configurationResolver->resolve($consumer, $input);

        $timeout = $configuration->getValue(Timeout::NAME);
        $maxRetries = $configuration->getValue(MaxRetries::NAME);
        $retryDelay = $configuration->getValue(RetryDelay::NAME);
        $maxRetryDelay = $configuration->getValue(MaxRetryDelay::NAME);
        $retryMultiplier = $configuration->getValue(RetryMultiplier::NAME);
        $topics = $configuration->getValue(Topics::NAME);
        $enableAutoCommit = $configuration->getValue(EnableAutoCommit::NAME);

        $rdKafkaConfig = $this->kafkaConfigurationFactory->create($consumer, $input);
        $rdKafkaConsumer = new RdKafkaConsumer($rdKafkaConfig);
        $rdKafkaConsumer->subscribe($topics);

        $consumptionStart = microtime(true);
        while (true) {
            try {
                $this->dispatch(PreMessageConsumedEvent::class, $consumer);

                $rdKafkaMessage = $rdKafkaConsumer->consume($timeout);
                $this->validateRdKafkaMessage($rdKafkaMessage);
            } catch (NullMessageException $exception) {
                $consumer->handleException($exception, new Context($configuration, 0));

                $this->setConsumptionTime($consumptionStart);

                continue;
            }

            for ($retry = 0; $retry <= $maxRetries; ++$retry) {
                $context = new Context($configuration, $retry);
                try {
                    $message = $this->messageFactory->create($rdKafkaMessage, $configuration);
                    $consumer->consume($message, $context);
                } catch (ValidationException | RecoverableMessageException $exception) {
                    $consumer->handleException($exception, $context);

                    if ($exception instanceof ValidationException) {
                        if ($enableAutoCommit === 'false') {
                            $rdKafkaConsumer->commit($rdKafkaMessage);
                        }

                        break;
                    }

                    if ($exception instanceof RecoverableMessageException) {
                        if ($retry !== $maxRetries) {
                            $retryDelay *= $retryMultiplier;
                            if ($retryDelay > $maxRetryDelay) {
                                $retryDelay = $maxRetryDelay;
                            }
                            usleep($retryDelay * 1000);
                        }

                        continue;
                    }
                }

                break;
            }

            $retryDelay = $configuration->getValue(RetryDelay::NAME);

            $this->increaseConsumedMessages();
            $this->setConsumptionTime($consumptionStart);

            $this->dispatch(PostMessageConsumedEvent::class, $consumer);
        }
    }

    private function setConsumptionTime(float $consumptionStart): void
    {
        $this->consumptionTimeMs = microtime(true) - $consumptionStart;
    }

    private function increaseConsumedMessages(): void
    {
        ++$this->consumedMessages;
    }

    private function validateRdKafkaMessage(?RdKafkaMessage $message): void
    {
        if (null === $message || RD_KAFKA_RESP_ERR__PARTITION_EOF === $message->err) {
            throw new NullMessageException('Currently, there are no more messages.');
        }

        if (RD_KAFKA_RESP_ERR__TIMED_OUT === $message->err) {
            throw new NullMessageException(
                'Kafka brokers have timed out or there are no messages. Unable to differentiate the reason.'
            );
        }

        if (null === $message->payload) {
            throw new NullMessageException('Null payload received in kafka message.');
        }
    }

    private function dispatch(string $eventClass, ConsumerInterface $consumer): void
    {
        if (!$this->dispatcher) {
            return;
        }

        switch ($eventClass) {
            case PostMessageConsumedEvent::class:
                $event = new PostMessageConsumedEvent($this->consumedMessages, $this->consumptionTimeMs);
                break;
            case PreMessageConsumedEvent::class:
                $event = new PreMessageConsumedEvent($this->consumedMessages, $this->consumptionTimeMs);
                break;
            default:
                throw new \RuntimeException(sprintf('Event class %s does not exist', $eventClass));
        }

        $this->dispatcher->dispatch($event, $event::getEventName($consumer->getName()));
        $this->dispatcher->dispatch($event);
    }
}
