<?php

namespace Imaximius\ElasticFilterBundle;

use Imaximius\WorkflowBundle\DependencyInjection\Compiler\WorkflowPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ImaximiusElasticFilterBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }
}
