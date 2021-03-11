<?php

declare(strict_types=1);

namespace Sts\KafkaBundle\DependencyInjection;

use Hoa\Protocol\Node\Node;
use Sts\KafkaBundle\Configuration\Type\AutoCommitIntervalMs;
use Sts\KafkaBundle\Configuration\Type\AutoOffsetReset;
use Sts\KafkaBundle\Configuration\Type\Brokers;
use Sts\KafkaBundle\Configuration\Type\Decoder;
use Sts\KafkaBundle\Configuration\Type\EnableAutoCommit;
use Sts\KafkaBundle\Configuration\Type\EnableAutoOffsetStore;
use Sts\KafkaBundle\Configuration\Type\GroupId;
use Sts\KafkaBundle\Configuration\Type\LogLevel;
use Sts\KafkaBundle\Configuration\Type\Offset;
use Sts\KafkaBundle\Configuration\Type\OffsetStoreMethod;
use Sts\KafkaBundle\Configuration\Type\Partition;
use Sts\KafkaBundle\Configuration\Type\ProducerPartition;
use Sts\KafkaBundle\Configuration\Type\RegisterMissingSchemas;
use Sts\KafkaBundle\Configuration\Type\RegisterMissingSubjects;
use Sts\KafkaBundle\Configuration\Type\SchemaRegistry;
use Sts\KafkaBundle\Configuration\Type\Timeout;
use Sts\KafkaBundle\Configuration\Type\Topics;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sts_kafka');
        $rootNode = $treeBuilder->getRootNode();

        $builder = $rootNode->children();

        $this->addBroker($builder)
            ->addSchemaRegistry($builder);

        $builder->append($this->addConsumersNode())
            ->append($this->addProducersNode());

        return $treeBuilder;
    }

    /**
     * @return \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition|\Symfony\Component\Config\Definition\Builder\NodeDefinition
     */
    private function addConsumersNode()
    {
        $consumersTreeBuilder = new TreeBuilder('consumers');
        $consumersNode = $consumersTreeBuilder->getRootNode();
        $consumersBuilder = $consumersNode->children();

        $this->addCommonConfigurations($consumersBuilder);
        $this->addConsumerConfigurations($consumersBuilder);

        $instancesTreeBuilder = new TreeBuilder('instances');
        $instancesNode = $instancesTreeBuilder->getRootNode();
        $instancesBuilder = $instancesNode->arrayPrototype()->children();
        $this->addCommonConfigurations($instancesBuilder);
        $this->addConsumerConfigurations($instancesBuilder);

        $consumersBuilder->append($instancesNode);

        return $consumersNode;
    }

    /**
     * @return \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition|\Symfony\Component\Config\Definition\Builder\NodeDefinition
     */
    private function addProducersNode()
    {
        $producersTreeBuilder = new TreeBuilder('producers');
        $producersNode = $producersTreeBuilder->getRootNode();
        $producersBuilder = $producersNode->children();

        $this->addCommonConfigurations($producersBuilder);
        $this->addProducerConfigurations($producersBuilder);

        $instancesTreeBuilder = new TreeBuilder('instances');
        $instancesNode = $instancesTreeBuilder->getRootNode();
        $instancesBuilder = $instancesNode->arrayPrototype()->children();
        $this->addCommonConfigurations($instancesBuilder);
        $this->addProducerConfigurations($instancesBuilder);

        $producersBuilder->append($instancesNode);

        return $producersNode;
    }

    public function addCommonConfigurations(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode(Decoder::NAME)
                ->defaultValue(Decoder::getDefaultValue())
                ->cannotBeEmpty()
            ->end()
            ->arrayNode(Topics::NAME)
                ->defaultValue(Topics::getDefaultValue())
                ->cannotBeEmpty()
                ->scalarPrototype()
                    ->cannotBeEmpty()
                ->end()
            ->end()
            ->integerNode(LogLevel::NAME)
                ->defaultValue(LogLevel::getDefaultValue())
            ->end();

        $this->addBroker($builder)
            ->addSchemaRegistry($builder);
    }

    private function addProducerConfigurations(NodeBuilder $builder)
    {
         $builder
            ->integerNode(ProducerPartition::NAME)
                ->defaultValue(ProducerPartition::getDefaultValue())
            ->end();
    }

    private function addConsumerConfigurations(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode(AutoCommitIntervalMs::NAME)
                ->defaultValue(AutoCommitIntervalMs::getDefaultValue())
                ->cannotBeEmpty()
            ->end()
            ->scalarNode(AutoOffsetReset::NAME)
                ->defaultValue(AutoOffsetReset::getDefaultValue())
                ->cannotBeEmpty()
            ->end()
            ->scalarNode(GroupId::NAME)
                ->defaultValue(GroupId::getDefaultValue())
                ->cannotBeEmpty()
            ->end()
            ->integerNode(Offset::NAME)
                ->defaultValue(Offset::getDefaultValue())
            ->end()
            ->scalarNode(OffsetStoreMethod::NAME)
                ->defaultValue(OffsetStoreMethod::getDefaultValue())
                ->cannotBeEmpty()
            ->end()
            ->integerNode(Partition::NAME)
                ->defaultValue(Partition::getDefaultValue())
            ->end()
            ->integerNode(Timeout::NAME)
                ->defaultValue(Timeout::getDefaultValue())
            ->end()
            ->scalarNode(EnableAutoOffsetStore::NAME)
                ->defaultValue(EnableAutoOffsetStore::getDefaultValue())
                ->cannotBeEmpty()
            ->end()
            ->scalarNode(EnableAutoCommit::NAME)
                ->defaultValue(EnableAutoCommit::getDefaultValue())
                ->cannotBeEmpty()
            ->end()
            ->booleanNode(RegisterMissingSchemas::NAME)
                ->defaultValue(RegisterMissingSchemas::getDefaultValue())
            ->end()
            ->booleanNode(RegisterMissingSubjects::NAME)
                ->defaultValue(RegisterMissingSubjects::getDefaultValue())
            ->end();

        $this->addBroker($builder);
    }

    private function addBroker(NodeBuilder $builder): self
    {
        $builder
            ->arrayNode(Brokers::NAME)
                ->defaultValue(Brokers::getDefaultValue())
                ->cannotBeEmpty()
                ->scalarPrototype()
                    ->cannotBeEmpty()
            ->end();

        return $this;
    }

    private function addSchemaRegistry(NodeBuilder $builder): self
    {
        $builder
            ->scalarNode(SchemaRegistry::NAME)
                ->defaultValue(SchemaRegistry::getDefaultValue())
                ->cannotBeEmpty()
            ->end();

        return $this;
    }
}
