<?php

namespace Imaximius\ElasticFilterBundle\Elasticsearch;

class NotNullFilter extends NullFilter
{
    /** @var mixed */
    protected $emptyFilter = 'is_empty';
    protected $operation = 'must_not';
}
