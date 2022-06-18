<?php

declare(strict_types=1);

namespace TheCodingMachine\GraphQLite\Laravel\Annotations;

use Attribute;
use BadMethodCallException;
use TheCodingMachine\GraphQLite\Annotations\MiddlewareAnnotationInterface;
use TheCodingMachine\GraphQLite\Annotations\ParameterAnnotationInterface;
use function is_string;
use function ltrim;

/**
 * Use this annotation to validate a parameter for a query or mutation.
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("for", type = "string"),
 *   @Attribute("rule", type = "string")
 * })
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Validate implements ParameterAnnotationInterface
{
    /** @var string */
    private $for;
    /** @var string */
    private $rule;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(string $for, string $rule)
    {
        $this->for = $for;
        $this->rule = $rule;
    }

    public function getTarget(): string
    {
        return $this->for;
    }

    public function getRule(): string
    {
        return $this->rule;
    }
}
