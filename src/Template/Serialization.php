<?php

declare(strict_types=1);

namespace Liip\Serializer\Template;

use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class Serialization
{
    private const TMPL_FUNCTION = <<<'EOT'
<?php

function {{functionName}}({{className}} $model, bool $useStdClass = true)
{
    $emptyHashmap = $useStdClass ? new \stdClass() : [];
    $emptyObject = $useStdClass ? new \stdClass() : [];
    $isPrimitive = function (mixed $data) {
        if (is_array($data)) {
            return false;
        }
    
        return null === $data || is_scalar($data);
    };
    

    {{code}}

    return $jsonData;
}

EOT;

    private const TMPL_CLASS = <<<'EOT'
{{target}} = [];
{{code}}
if ([] === {{target}}) {
    {{target}} = $emptyObject;
}

EOT;

    private const TMPL_CONDITIONAL = <<<'EOT'
if (null !== {{condition}}) {
    {{code}}
}
{%- if elseCode -%}
else {
    {{elseCode}}
}
{% endif %}

EOT;

    private const TMPL_INSTANCE_OF_CONDITIONAL = <<<'EOT'
if ({{propertyAccessor}} instanceof {{class}}) {
    {{code}}
}

EOT;

    private const TMPL_PRIMITIVE_CONDITIONAL = <<<'EOT'
if ($isPrimitive({{propertyAccessor}})) {
    {{code}}
}
EOT;

    private const TMPL_ARRAY_CONDITIONAL = <<<'EOT'
if (is_array({{propertyAccessor}})) {
    {{code}}
}
EOT;

    private const TMPL_ASSIGN = <<<'EOT'
{{target}} = {{propertyAccessor}};
EOT;

    private const TMPL_ARRAY_ASSIGN = <<<'EOT'
{{target}} = is_array({{propertyAccessor}}) ? {{propertyAccessor}} : iterator_to_array({{propertyAccessor}});
EOT;

    private const TMPL_LOOP_ARRAY = <<<'EOT'
{{target}} = [];
foreach ({{propertyAccessor}} as {{indexVariable}} => {{valueVariable}}) {
    {{code}}
}

EOT;

    private const TMPL_LOOP_ARRAY_EMPTY = <<<'EOT'
{{target}} = [];

EOT;

    private const TMPL_LOOP_HASHMAP = <<<'EOT'
if (0 === \count({{propertyAccessor}})) {
    {{target}} = $emptyHashmap;
} else {
    {{target}} = [];
    foreach ({{propertyAccessor}} as {{indexVariable}} => {{valueVariable}}) {
        {{code}}
    }
}

EOT;

    private const TMPL_GETTER = '{{modelPath}}->{{method}}()';

    private const TMPL_DATETIME = '{{propertyPath}}{{ nullable ? "?" : "" }}->format(\'{{format}}\')';

    private const TMPL_TEMP_VAR = '${{name}} = {{value}}';

    private Environment $twig;

    public function __construct()
    {
        $this->twig = new Environment(new ArrayLoader(), ['autoescape' => false]);
    }

    public function renderFunction(string $name, string $className, string $code): string
    {
        return $this->render(self::TMPL_FUNCTION, [
            'functionName' => $name,
            'className' => $className,
            'code' => $code,
        ]);
    }

    public function renderClass(string $target, string $code): string
    {
        return $this->render(self::TMPL_CLASS, [
            'target' => $target,
            'code' => $code,
        ]);
    }

    public function renderConditional(string $condition, string $code, ?string $elseCode = null): string
    {
        return $this->render(self::TMPL_CONDITIONAL, [
            'condition' => $condition,
            'code' => $code,
            'elseCode' => $elseCode,
        ]);
    }

    public function renderInstanceOfConditional(string $propertyAccessor, string $class, string $code): string
    {
        return $this->render(self::TMPL_INSTANCE_OF_CONDITIONAL, [
            'propertyAccessor' => $propertyAccessor,
            'class' => $class,
            'code' => $code,
        ]);
    }

    public function renderPrimitiveConditional(string $propertyAccessor, string $code): string
    {
        return $this->render(self::TMPL_PRIMITIVE_CONDITIONAL, [
            'propertyAccessor' => $propertyAccessor,
            'code' => $code,
        ]);
    }

    public function renderArrayConditional(string $propertyAccessor, string $code): string
    {
        return $this->render(self::TMPL_ARRAY_CONDITIONAL, [
            'propertyAccessor' => $propertyAccessor,
            'code' => $code,
        ]);
    }

    public function renderAssign(string $target, string $propertyAccessor): string
    {
        return $this->render(self::TMPL_ASSIGN, [
            'target' => $target,
            'propertyAccessor' => $propertyAccessor,
        ]);
    }

    public function renderArrayAssign(string $target, string $propertyAccessor): string
    {
        return $this->render(self::TMPL_ARRAY_ASSIGN, [
            'target' => $target,
            'propertyAccessor' => $propertyAccessor,
        ]);
    }

    public function renderLoopArray(string $target, string $propertyAccessor, string $indexVariable, string $valueVariable, string $code): string
    {
        return $this->render(self::TMPL_LOOP_ARRAY, [
            'target' => $target,
            'propertyAccessor' => $propertyAccessor,
            'indexVariable' => $indexVariable,
            'valueVariable' => $valueVariable,
            'code' => $code,
        ]);
    }

    public function renderLoopArrayEmpty(string $target): string
    {
        return $this->render(self::TMPL_LOOP_ARRAY_EMPTY, [
            'target' => $target,
        ]);
    }

    public function renderLoopHashmap(string $target, string $propertyAccessor, string $indexVariable, string $valueVariable, string $code): string
    {
        return $this->render(self::TMPL_LOOP_HASHMAP, [
            'target' => $target,
            'propertyAccessor' => $propertyAccessor,
            'indexVariable' => $indexVariable,
            'valueVariable' => $valueVariable,
            'code' => $code,
        ]);
    }

    public function renderLoopHashmapEmpty(string $target): string
    {
        return $this->render(self::TMPL_LOOP_HASHMAP, [
            'target' => $target,
        ]);
    }

    public function renderGetter(string $modelPath, string $method): string
    {
        return $this->render(self::TMPL_GETTER, [
            'modelPath' => $modelPath,
            'method' => $method,
        ]);
    }

    public function renderDateTime(string $propertyPath, string $format, bool $nullable = false): string
    {
        return $this->render(self::TMPL_DATETIME, [
            'propertyPath' => $propertyPath,
            'format' => $format,
            'nullable' => $nullable,
        ]);
    }

    public function renderTempVariable(string $name, string $value): string
    {
        return $this->render(self::TMPL_TEMP_VAR, [
            'name' => $name,
            'value' => $value,
        ]);
    }

    public function renderConditionalUsingTempVariable(string $tempVariable, string $propertyAccessor, string $code): string
    {
        return $this->render(self::TMPL_CONDITIONAL, [
            'condition' => $this->renderTempVariable($tempVariable, $propertyAccessor),
            'code' => $code,
        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function render(string $template, array $parameters): string
    {
        $tmpl = $this->twig->createTemplate($template);

        return $tmpl->render($parameters);
    }
}
