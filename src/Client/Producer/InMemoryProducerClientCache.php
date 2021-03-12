<?php

declare(strict_types=1);

namespace Sts\KafkaBundle\Client\Producer;

use RdKafka\Conf;
use RdKafka\Producer as RdKafkaProducer;
use RdKafka\ProducerTopic;
use Sts\KafkaBundle\Client\Contract\ProducerMessageInterface;
use Sts\KafkaBundle\Client\Traits\CheckProducerTopic;
use Sts\KafkaBundle\Configuration\ConfigurationResolver;
use Sts\KafkaBundle\Configuration\ResolvedConfiguration;
use Sts\KafkaBundle\Configuration\Type\Topics;
use Sts\KafkaBundle\RdKafka\Factory\GlobalConfigurationFactory;
use Symfony\Component\Console\Input\InputInterface;

class InMemoryProducerClientCache
{
    use CheckProducerTopic;

    private GlobalConfigurationFactory $globalConfigurationFactory;
    private ConfigurationResolver $configurationResolver;

    /**
     * @var array<ResolvedConfiguration>
     */
    private array $resolvedConfigurations = [];

    /**
     * @var array<Conf>
     */
    private array $rdKafkaConfigurations = [];

    /**
     * @var array<RdKafkaProducer>
     */
    private array $rdKafkaProducers = [];

    /**
     * @var array<ProducerTopic>
     */
    private array $producerTopics = [];

    public function __construct(
        GlobalConfigurationFactory $globalConfigurationFactory,
        ConfigurationResolver $configurationResolver
    ) {
        $this->globalConfigurationFactory = $globalConfigurationFactory;
        $this->configurationResolver = $configurationResolver;
    }

    public function getResolvedConfiguration(string $producer, ?InputInterface $input = null): ResolvedConfiguration
    {
        if (!array_key_exists($producer, $this->resolvedConfigurations)) {
            $this->resolvedConfigurations[$producer] = $this->configurationResolver->resolve($producer, $input);
        }

        return $this->resolvedConfigurations[$producer];
    }

    public function getRdKafkaConfiguration(string $producer, ResolvedConfiguration $resolvedConfiguration): Conf
    {
        if (!array_key_exists($producer, $this->rdKafkaConfigurations)) {
            $this->rdKafkaConfigurations[$producer] = $this->globalConfigurationFactory->create($resolvedConfiguration);
        }

        return $this->rdKafkaConfigurations[$producer];
    }

    public function getRdKafkaProducer(string $producer, Conf $rdKafkaConfiguration): RdKafkaProducer
    {
        if (!array_key_exists($producer, $this->rdKafkaProducers)) {
            $this->rdKafkaProducers[$producer] = new RdKafkaProducer($rdKafkaConfiguration);
        }

        return $this->rdKafkaProducers[$producer];
    }

    /**
     * @param string $producer
     * @param ResolvedConfiguration $resolvedConfiguration
     * @param RdKafkaProducer $rdKafkaProducer
     * @return array<ProducerTopic>
     */
    public function getProducerTopics(
        string $producer,
        ResolvedConfiguration $resolvedConfiguration,
        RdKafkaProducer $rdKafkaProducer
    ): array {
        if (!array_key_exists($producer, $this->producerTopics)) {
            foreach ($resolvedConfiguration->getConfigurationValue(Topics::NAME) as $topic) {
                $this->isTopicBlacklisted($topic);
                $this->producerTopics[$producer][] = $rdKafkaProducer->newTopic($topic);
            }
        }

        return $this->producerTopics[$producer];
    }
}
