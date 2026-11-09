<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Traversal;

use Inilim\SqliteParser\Ast\Node;
use ReflectionObject;
use ReflectionProperty;

/**
 * Трансформер деревьев AST.
 *
 * Пользователь передаёт замыкание `fn(Node $node): ?Node`. Возвращаемое значение:
 *
 *   - тот же самый инстанс `$node` — узел не изменяется;
 *   - другой `Node`                — узел заменяется на новый;
 *   - `null`                        — узел удаляется. Для nullable-свойств
 *                                     он становится `null`, для массивов —
 *                                     элемент выпадает из списка. Удаление
 *                                     обязательного (non-nullable) свойства
 *                                     бросает \LogicException — в этом случае
 *                                     нужно удалять родительский узел целиком.
 *
 * Обход post-order: сначала трансформируются дети, потом сам узел. Это
 * позволяет безопасно переписывать и удалять глубоко вложенные узлы без потери
 * контекста.
 *
 * Примеры:
 *
 *     // Удалить все звёздочки из дерева:
 *     $t = new NodeTransformer(
 *         static fn(Node $n): ?Node
 *             => $n instanceof Star ? null : $n
 *     );
 *     $newScript = $t->transform($script);
 *
 *     // Переименовать все ссылки на колонку "old" в "new":
 *     $t = new NodeTransformer(
 *         static fn(Node $n): Node
 *             => $n instanceof ColumnReference && $n->name === 'old'
 *                 ? NodeCloner::with($n, ['name' => 'new'])
 *                 : $n
 *     );
 *
 *     // Удалить WHERE-условие из SelectStatement:
 *     $t = new NodeTransformer(
 *         static fn(Node $n): ?Node
 *             => $n instanceof SelectStatement && $n->where !== null
 *                 ? NodeCloner::with($n, ['where' => null])
 *                 : $n
 *     );
 */
final class NodeTransformer
{
    /** @var \Closure(Node): ?Node */
    private \Closure $transformer;

    /**
     * @param \Closure(Node): ?Node $transformer
     */
    public function __construct(\Closure $transformer)
    {
        $this->transformer = $transformer;
    }

    public function transform(Node $root): ?Node
    {
        return $this->processNode($root);
    }

    private function processNode(Node $node): ?Node
    {
        $node = $this->rebuildChildren($node);

        return ($this->transformer)($node);
    }

    private function rebuildChildren(Node $node): Node
    {
        $ref = new ReflectionObject($node);
        $overrides = [];

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!$prop->isInitialized($node)) {
                continue;
            }

            $name = $prop->getName();
            $value = $prop->getValue($node);

            $newValue = $this->transformValue($value, $prop, $name);
            if ($newValue !== $value) {
                $overrides[$name] = $newValue;
            }
        }

        if ($overrides === []) {
            return $node;
        }

        return NodeCloner::with($node, $overrides);
    }

    private function transformValue(mixed $value, ?ReflectionProperty $prop, string $propName): mixed
    {
        if ($value instanceof Node) {
            $new = $this->processNode($value);

            if ($new === $value) {
                return $value;
            }

            if ($new === null) {
                if ($prop !== null) {
                    $type = $prop->getType();
                    $nullable = $type === null || $type->allowsNull();
                    if (!$nullable) {
                        throw new \LogicException(sprintf(
                            'Cannot delete required property %s::$%s — delete the parent node instead.',
                            $prop->getDeclaringClass()->getName(),
                            $propName,
                        ));
                    }
                }

                return null;
            }

            return $new;
        }

        if (is_array($value)) {
            $isList = array_is_list($value);
            $result = [];
            $changed = false;

            foreach ($value as $key => $item) {
                $new = $this->transformValue($item, null, $propName);

                if ($new === null && $item !== null) {
                    $changed = true;
                    continue;
                }

                if ($new !== $item) {
                    $changed = true;
                }

                if ($isList) {
                    $result[] = $new;
                } else {
                    $result[$key] = $new;
                }
            }

            return $changed ? $result : $value;
        }

        return $value;
    }
}