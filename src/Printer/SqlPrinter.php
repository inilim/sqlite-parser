<?php

declare(strict_types=1);

namespace Inilim\SqliteParser\Printer;

use Inilim\SqliteParser\Ast\Expression;
use Inilim\SqliteParser\Ast\Expression\BetweenExpression;
use Inilim\SqliteParser\Ast\Expression\BinaryOperation;
use Inilim\SqliteParser\Ast\Expression\CaseExpression;
use Inilim\SqliteParser\Ast\Expression\CastExpression;
use Inilim\SqliteParser\Ast\Expression\ColumnReference;
use Inilim\SqliteParser\Ast\Expression\ExpressionList;
use Inilim\SqliteParser\Ast\Expression\FunctionCall;
use Inilim\SqliteParser\Ast\Expression\InExpression;
use Inilim\SqliteParser\Ast\Expression\IsNullExpression;
use Inilim\SqliteParser\Ast\Expression\LikeExpression;
use Inilim\SqliteParser\Ast\Expression\Literal;
use Inilim\SqliteParser\Ast\Expression\Star;
use Inilim\SqliteParser\Ast\Expression\Subquery;
use Inilim\SqliteParser\Ast\Expression\UnaryOperation;
use Inilim\SqliteParser\Ast\Expression\WhenClause;
use Inilim\SqliteParser\Ast\Expression\WindowFunction;
use Inilim\SqliteParser\Ast\Node;
use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement\AlterTableStatement;
use Inilim\SqliteParser\Ast\Statement\Assignment;
use Inilim\SqliteParser\Ast\Statement\AssignmentList;
use Inilim\SqliteParser\Ast\Statement\AttachStatement;
use Inilim\SqliteParser\Ast\Statement\ColumnConstraint;
use Inilim\SqliteParser\Ast\Statement\ColumnDefinition;
use Inilim\SqliteParser\Ast\Statement\CommonTableExpression;
use Inilim\SqliteParser\Ast\Statement\CompoundSelect;
use Inilim\SqliteParser\Ast\Statement\CreateIndexStatement;
use Inilim\SqliteParser\Ast\Statement\CreateTableStatement;
use Inilim\SqliteParser\Ast\Statement\CreateTriggerStatement;
use Inilim\SqliteParser\Ast\Statement\CreateViewStatement;
use Inilim\SqliteParser\Ast\Statement\DeleteStatement;
use Inilim\SqliteParser\Ast\Statement\DetachStatement;
use Inilim\SqliteParser\Ast\Statement\DropStatement;
use Inilim\SqliteParser\Ast\Statement\EmptyStatement;
use Inilim\SqliteParser\Ast\Statement\ExplainStatement;
use Inilim\SqliteParser\Ast\Statement\ForeignKeyAction;
use Inilim\SqliteParser\Ast\Statement\ForeignKeyClause;
use Inilim\SqliteParser\Ast\Statement\FrameBound;
use Inilim\SqliteParser\Ast\Statement\FrameSpec;
use Inilim\SqliteParser\Ast\Statement\FromClause;
use Inilim\SqliteParser\Ast\Statement\GroupByClause;
use Inilim\SqliteParser\Ast\Statement\HavingClause;
use Inilim\SqliteParser\Ast\Statement\IdentListWrapper;
use Inilim\SqliteParser\Ast\Statement\InsertDefaultBody;
use Inilim\SqliteParser\Ast\Statement\InsertStatement;
use Inilim\SqliteParser\Ast\Statement\InsertValuesBody;
use Inilim\SqliteParser\Ast\Statement\JoinClause;
use Inilim\SqliteParser\Ast\Statement\JoinConstraint;
use Inilim\SqliteParser\Ast\Statement\OrderBy;
use Inilim\SqliteParser\Ast\Statement\OrderByList;
use Inilim\SqliteParser\Ast\Statement\PragmaStatement;
use Inilim\SqliteParser\Ast\Statement\ResultColumn;
use Inilim\SqliteParser\Ast\Statement\ResultColumnList;
use Inilim\SqliteParser\Ast\Statement\ReturningClause;
use Inilim\SqliteParser\Ast\Statement\SavepointStatement;
use Inilim\SqliteParser\Ast\Statement\SelectStatement;
use Inilim\SqliteParser\Ast\Statement\TableConstraint;
use Inilim\SqliteParser\Ast\Statement\TableOptions;
use Inilim\SqliteParser\Ast\Statement\TableReference;
use Inilim\SqliteParser\Ast\Statement\TransactionStatement;
use Inilim\SqliteParser\Ast\Statement\TriggerBody;
use Inilim\SqliteParser\Ast\Statement\TriggerEventInfo;
use Inilim\SqliteParser\Ast\Statement\UpdateStatement;
use Inilim\SqliteParser\Ast\Statement\UpsertClause;
use Inilim\SqliteParser\Ast\Statement\WhereClause;
use Inilim\SqliteParser\Ast\Statement\WindowSpecification;
use Inilim\SqliteParser\Ast\Statement\WithClause;

/**
 * Собирает из AST обратно SQL-строку.
 *
 * Пример:
 *
 *     $printer = new SqlPrinter();
 *     $sql = $printer->print($script); // "SELECT id FROM users"
 */
final class SqlPrinter
{
    private const RESERVED = [
        'SELECT',
        'FROM',
        'WHERE',
        'JOIN',
        'ON',
        'AS',
        'AND',
        'OR',
        'NOT',
        'NULL',
        'IS',
        'IN',
        'BETWEEN',
        'LIKE',
        'GLOB',
        'CASE',
        'WHEN',
        'THEN',
        'ELSE',
        'END',
        'CAST',
        'INSERT',
        'UPDATE',
        'DELETE',
        'INTO',
        'VALUES',
        'SET',
        'CREATE',
        'TABLE',
        'INDEX',
        'VIEW',
        'TRIGGER',
        'DROP',
        'ALTER',
        'ADD',
        'RENAME',
        'TO',
        'COLUMN',
        'PRIMARY',
        'KEY',
        'FOREIGN',
        'REFERENCES',
        'UNIQUE',
        'CHECK',
        'DEFAULT',
        'COLLATE',
        'AUTOINCREMENT',
        'TEMP',
        'TEMPORARY',
        'IF',
        'EXISTS',
        'WITHOUT',
        'ROWID',
        'STRICT',
        'ORDER',
        'BY',
        'GROUP',
        'HAVING',
        'LIMIT',
        'OFFSET',
        'DISTINCT',
        'ALL',
        'UNION',
        'INTERSECT',
        'EXCEPT',
        'LEFT',
        'RIGHT',
        'FULL',
        'INNER',
        'OUTER',
        'CROSS',
        'NATURAL',
        'USING',
        'BEGIN',
        'COMMIT',
        'ROLLBACK',
        'TRANSACTION',
        'SAVEPOINT',
        'RELEASE',
        'PRAGMA',
        'ATTACH',
        'DETACH',
        'DATABASE',
        'EXPLAIN',
        'QUERY',
        'PLAN',
        'RECURSIVE',
        'RETURNING',
        'CONFLICT',
        'DO',
        'NOTHING',
        'OVER',
        'PARTITION',
        'RANGE',
        'ROWS',
        'PRECEDING',
        'FOLLOWING',
        'CURRENT',
        'ROW',
        'UNBOUNDED',
        'BEFORE',
        'AFTER',
        'INSTEAD',
        'OF',
        'FOR',
        'EACH',
        'CASCADE',
        'RESTRICT',
        'NO',
        'ACTION',
        'DEFERRED',
        'IMMEDIATE',
        'EXCLUSIVE',
        'REPLACE',
        'IGNORE',
        'ABORT',
        'FAIL',
        'TEXT',
        'INTEGER',
        'REAL',
        'BLOB',
        'NUMERIC',
    ];

    private const PRECEDENCE = [
        'OR' => 1,
        'AND' => 2,
        '=' => 3,
        '==' => 3,
        '!=' => 3,
        '<>' => 3,
        '<' => 3,
        '<=' => 3,
        '>' => 3,
        '>=' => 3,
        'IS' => 3,
        'IS NOT' => 3,
        'LIKE' => 3,
        'NOT LIKE' => 3,
        'GLOB' => 3,
        'NOT GLOB' => 3,
        'BETWEEN' => 3,
        'NOT BETWEEN' => 3,
        'IN' => 3,
        'NOT IN' => 3,
        '|' => 4,
        '&' => 5,
        '<<' => 6,
        '>>' => 6,
        '+' => 7,
        '-' => 7,
        '*' => 8,
        '/' => 8,
        '%' => 8,
        '||' => 9,
    ];

    private const ASSOCIATIVE = ['+', '*', '||', 'AND', 'OR', '&', '|', '<<', '>>'];

    public function print(Node $node): string
    {
        return $this->render($node);
    }

    public function format(Node $node): string
    {
        return $this->print($node);
    }

    private function render(Node $node): string
    {
        return match (true) {
            $node instanceof SelectStatement => $this->renderSelect($node),
            $node instanceof CompoundSelect => $this->renderCompoundSelect($node),
            $node instanceof InsertStatement => $this->renderInsert($node),
            $node instanceof UpdateStatement => $this->renderUpdate($node),
            $node instanceof DeleteStatement => $this->renderDelete($node),
            $node instanceof CreateTableStatement => $this->renderCreateTable($node),
            $node instanceof CreateIndexStatement => $this->renderCreateIndex($node),
            $node instanceof CreateViewStatement => $this->renderCreateView($node),
            $node instanceof CreateTriggerStatement => $this->renderCreateTrigger($node),
            $node instanceof DropStatement => $this->renderDrop($node),
            $node instanceof AlterTableStatement => $this->renderAlterTable($node),
            $node instanceof TransactionStatement => $this->renderTransaction($node),
            $node instanceof SavepointStatement => $this->renderSavepoint($node),
            $node instanceof AttachStatement => $this->renderAttach($node),
            $node instanceof DetachStatement => $this->renderDetach($node),
            $node instanceof PragmaStatement => $this->renderPragma($node),
            $node instanceof ExplainStatement => $this->renderExplain($node),
            $node instanceof EmptyStatement => '',
            $node instanceof Literal => $this->renderLiteral($node),
            $node instanceof ColumnReference => $this->renderColumnReference($node),
            $node instanceof Star => '*',
            $node instanceof BinaryOperation => $this->renderBinary($node),
            $node instanceof UnaryOperation => $this->renderUnary($node),
            $node instanceof BetweenExpression => $this->renderBetween($node),
            $node instanceof InExpression => $this->renderIn($node),
            $node instanceof IsNullExpression => $this->renderIsNull($node),
            $node instanceof LikeExpression => $this->renderLike($node),
            $node instanceof CastExpression => $this->renderCast($node),
            $node instanceof CaseExpression => $this->renderCase($node),
            $node instanceof FunctionCall => $this->renderFunctionCall($node),
            $node instanceof WindowFunction => $this->renderWindowFunction($node),
            $node instanceof Subquery => '(' . $this->render($node->query) . ')',
            $node instanceof ExpressionList => $this->renderList($node->items),
            $node instanceof WhenClause => $this->renderWhenClause($node),
            $node instanceof Assignment => $this->renderAssignment($node),
            $node instanceof AssignmentList => $this->renderList($node->items),
            $node instanceof OrderBy => $this->renderOrderBy($node),
            $node instanceof OrderByList => $this->renderList($node->items),
            $node instanceof ResultColumn => $this->renderResultColumn($node),
            $node instanceof ResultColumnList => $this->renderList($node->items),
            $node instanceof ReturningClause => 'RETURNING ' . $this->renderList($node->columns),
            $node instanceof WithClause => $this->renderWithClause($node),
            $node instanceof CommonTableExpression => $this->renderCte($node),
            $node instanceof TableReference => $this->renderTableReference($node),
            $node instanceof JoinClause => $this->renderJoinClause($node),
            $node instanceof JoinConstraint => $this->renderJoinConstraint($node),
            $node instanceof FromClause => $this->renderFromClause($node),
            $node instanceof GroupByClause => 'GROUP BY ' . $this->renderList($node->expressions),
            $node instanceof HavingClause => 'HAVING ' . $this->render($node->expression),
            $node instanceof WhereClause => 'WHERE ' . $this->render($node->expression),
            $node instanceof ColumnDefinition => $this->renderColumnDefinition($node),
            $node instanceof ColumnConstraint => $this->renderColumnConstraint($node),
            $node instanceof TableConstraint => $this->renderTableConstraint($node),
            $node instanceof ForeignKeyClause => $this->renderForeignKeyClause($node),
            $node instanceof ForeignKeyAction => $this->renderForeignKeyAction($node),
            $node instanceof TableOptions => $this->renderTableOptions($node),
            $node instanceof UpsertClause => $this->renderUpsertClause($node),
            $node instanceof WindowSpecification => $this->renderWindowSpec($node),
            $node instanceof FrameSpec => $this->renderFrameSpec($node),
            $node instanceof FrameBound => $this->renderFrameBound($node),
            $node instanceof IdentListWrapper => $this->renderIdentList($node->items),
            $node instanceof InsertValuesBody => $this->renderInsertValuesBody($node),
            $node instanceof InsertDefaultBody => 'DEFAULT VALUES',
            $node instanceof TriggerBody => $this->renderTriggerBody($node),
            $node instanceof TriggerEventInfo => $node->event,
            default => throw new \RuntimeException('Cannot render node: ' . $node::class),
        };
    }

    private function renderSelect(SelectStatement $s): string
    {
        $parts = [];

        if ($s->with !== null) {
            $parts[] = $this->renderWithClause($s->with);
        }

        $select = 'SELECT ';
        if ($s->distinct) {
            $select .= 'DISTINCT ';
        }
        $select .= $this->renderList($s->columns);
        $parts[] = $select;

        if ($s->from !== null) {
            $from = 'FROM ' . $this->renderTableReference($s->from);
            foreach ($s->joins as $join) {
                $from .= ' ' . $this->renderJoinClause($join);
            }
            $parts[] = $from;
        }

        if ($s->where !== null) {
            $parts[] = 'WHERE ' . $this->render($s->where);
        }

        if ($s->groupBy !== null) {
            $parts[] = 'GROUP BY ' . $this->renderList($s->groupBy->expressions);
        }

        if ($s->having !== null) {
            $parts[] = 'HAVING ' . $this->render($s->having);
        }

        if ($s->orderBy !== null) {
            $parts[] = 'ORDER BY ' . $this->renderList($s->orderBy);
        }

        if ($s->limit !== null) {
            $limit = 'LIMIT ' . $s->limit['limit'];
            if ($s->limit['offset'] !== null) {
                $limit .= ' OFFSET ' . $s->limit['offset'];
            }
            $parts[] = $limit;
        }

        return implode(' ', $parts);
    }

    private function renderCompoundSelect(CompoundSelect $s): string
    {
        $parts = [];

        if ($s->with !== null) {
            $parts[] = $this->renderWithClause($s->with);
        }

        $sel = '';
        foreach ($s->selects as $i => $select) {
            if ($i === 0) {
                $sel .= $this->renderSelect($select);
            } else {
                $op = $s->operators[$i - 1] ?? 'UNION';
                $sel .= ' ' . $op . ' ' . $this->renderSelect($select);
            }
        }
        $parts[] = $sel;

        if ($s->orderBy !== null) {
            $parts[] = 'ORDER BY ' . $this->renderList($s->orderBy);
        }

        if ($s->limit !== null) {
            $limit = 'LIMIT ' . $s->limit['limit'];
            if ($s->limit['offset'] !== null) {
                $limit .= ' OFFSET ' . $s->limit['offset'];
            }
            $parts[] = $limit;
        }

        return implode(' ', $parts);
    }

    private function renderInsert(InsertStatement $ins): string
    {
        $sql = 'INSERT ';

        if ($ins->orAction !== null) {
            $sql .= 'OR ' . $ins->orAction . ' ';
        }

        $sql .= 'INTO ' . $this->ident($ins->table);

        if ($ins->columns !== null) {
            $sql .= ' (' . $this->renderIdentList($ins->columns) . ')';
        }

        if ($ins->rows !== null) {
            $rows = [];
            foreach ($ins->rows as $row) {
                $rows[] = '(' . $this->renderList($row) . ')';
            }
            $sql .= ' VALUES ' . implode(', ', $rows);
        } elseif ($ins->select !== null) {
            $sql .= ' ' . $this->render($ins->select);
        } elseif ($ins->defaultValues) {
            $sql .= ' DEFAULT VALUES';
        }

        if ($ins->upsert !== null) {
            $sql .= ' ' . $this->renderUpsertClause($ins->upsert);
        }

        if ($ins->returning !== null) {
            $sql .= ' ' . $this->renderReturningClause($ins->returning);
        }

        return $sql;
    }

    private function renderUpdate(UpdateStatement $u): string
    {
        $sql = 'UPDATE ';
        if ($u->orAction !== null) {
            $sql .= 'OR ' . $u->orAction . ' ';
        }
        $sql .= $this->ident($u->table);
        $sql .= ' SET ' . $this->renderList($u->assignments);

        if ($u->where !== null) {
            $sql .= ' WHERE ' . $this->render($u->where);
        }

        if ($u->returning !== null) {
            $sql .= ' ' . $this->renderReturningClause($u->returning);
        }

        return $sql;
    }

    private function renderDelete(DeleteStatement $d): string
    {
        $sql = 'DELETE FROM ' . $this->ident($d->table);

        if ($d->where !== null) {
            $sql .= ' WHERE ' . $this->render($d->where);
        }

        if ($d->returning !== null) {
            $sql .= ' ' . $this->renderReturningClause($d->returning);
        }

        return $sql;
    }

    private function renderCreateTable(CreateTableStatement $ct): string
    {
        $sql = 'CREATE ';
        if ($ct->temporary) {
            $sql .= 'TEMP ';
        }
        $sql .= 'TABLE ';
        if ($ct->ifNotExists) {
            $sql .= 'IF NOT EXISTS ';
        }
        $sql .= $this->ident($ct->table);

        if ($ct->asSelect !== null) {
            return $sql . ' AS ' . $this->render($ct->asSelect);
        }

        $defs = [];
        foreach ($ct->columns as $col) {
            $defs[] = $this->renderColumnDefinition($col);
        }
        foreach ($ct->tableConstraints as $tc) {
            $defs[] = $this->renderTableConstraint($tc);
        }
        $sql .= ' (' . implode(', ', $defs) . ')';

        if ($ct->withoutRowid || $ct->strict) {
            $opts = [];
            if ($ct->withoutRowid) {
                $opts[] = 'WITHOUT ROWID';
            }
            if ($ct->strict) {
                $opts[] = 'STRICT';
            }
            $sql .= ' ' . implode(', ', $opts);
        }

        return $sql;
    }

    private function renderCreateIndex(CreateIndexStatement $ci): string
    {
        $sql = 'CREATE ';
        if ($ci->unique) {
            $sql .= 'UNIQUE ';
        }
        $sql .= 'INDEX ';
        if ($ci->ifNotExists) {
            $sql .= 'IF NOT EXISTS ';
        }
        $sql .= $this->ident($ci->name) . ' ON ' . $this->ident($ci->table);
        $sql .= ' (' . $this->renderIdentList($ci->columns) . ')';
        if ($ci->where !== null) {
            $sql .= ' WHERE ' . $this->render($ci->where);
        }

        return $sql;
    }

    private function renderCreateView(CreateViewStatement $cv): string
    {
        $sql = 'CREATE ';
        if ($cv->temporary) {
            $sql .= 'TEMP ';
        }
        $sql .= 'VIEW ';
        if ($cv->ifNotExists) {
            $sql .= 'IF NOT EXISTS ';
        }
        $sql .= $this->ident($cv->name);
        if ($cv->columns !== null) {
            $sql .= ' (' . $this->renderIdentList($cv->columns) . ')';
        }
        $sql .= ' AS ' . $this->render($cv->select);

        return $sql;
    }

    private function renderCreateTrigger(CreateTriggerStatement $ct): string
    {
        $sql = 'CREATE ';
        if ($ct->temporary) {
            $sql .= 'TEMP ';
        }
        $sql .= 'TRIGGER ';
        if ($ct->ifNotExists) {
            $sql .= 'IF NOT EXISTS ';
        }
        $sql .= $this->ident($ct->name);
        $sql .= ' ' . $ct->timing . ' ' . $ct->event;
        if ($ct->event === 'UPDATE' && $ct->updateOf !== []) {
            $sql .= ' OF ' . $this->renderIdentList($ct->updateOf);
        }
        $sql .= ' ON ' . $this->ident($ct->table);
        if ($ct->forEachRow) {
            $sql .= ' FOR EACH ROW';
        }
        if ($ct->when !== null) {
            $sql .= ' WHEN ' . $this->render($ct->when);
        }

        $stmts = [];
        foreach ($ct->statements as $stmt) {
            $r = $this->render($stmt);
            if ($r !== '') {
                $stmts[] = $r;
            }
        }
        $sql .= ' BEGIN ' . implode('; ', $stmts) . '; END';

        return $sql;
    }

    private function renderDrop(DropStatement $d): string
    {
        $sql = 'DROP ' . $d->objectType . ' ';
        if ($d->ifExists) {
            $sql .= 'IF EXISTS ';
        }
        $sql .= $this->ident($d->name);

        return $sql;
    }

    private function renderAlterTable(AlterTableStatement $a): string
    {
        $sql = 'ALTER TABLE ' . $this->ident($a->table);

        switch ($a->action) {
            case 'RENAME_TO':
                $sql .= ' RENAME TO ' . $this->ident($a->newName ?? '');
                break;
            case 'RENAME_COLUMN':
                $sql .= ' RENAME COLUMN ' . $this->ident($a->oldName ?? '')
                    . ' TO ' . $this->ident($a->newName ?? '');
                break;
            case 'ADD_COLUMN':
                $sql .= ' ADD COLUMN '
                    . ($a->column !== null ? $this->renderColumnDefinition($a->column) : '');
                break;
            case 'DROP_COLUMN':
                $sql .= ' DROP COLUMN ' . $this->ident($a->oldName ?? '');
                break;
            default:
                $sql .= ' ' . $a->action;
        }

        return $sql;
    }

    private function renderTransaction(TransactionStatement $t): string
    {
        switch ($t->type) {
            case 'BEGIN':
                $sql = 'BEGIN';
                if ($t->mode !== '') {
                    $sql .= ' ' . $t->mode;
                }
                if ($t->name !== null) {
                    $sql .= ' TRANSACTION ' . $this->ident($t->name);
                }
                return $sql;

            case 'COMMIT':
                $sql = 'COMMIT';
                if ($t->name !== null) {
                    $sql .= ' TRANSACTION ' . $this->ident($t->name);
                }
                return $sql;

            case 'END':
                $sql = 'END TRANSACTION';
                if ($t->name !== null) {
                    $sql .= ' ' . $this->ident($t->name);
                }
                return $sql;

            case 'ROLLBACK':
                if ($t->toSavepoint) {
                    return 'ROLLBACK TO SAVEPOINT ' . $this->ident($t->name ?? '');
                }
                return 'ROLLBACK TRANSACTION';
        }

        return $t->type;
    }

    private function renderSavepoint(SavepointStatement $s): string
    {
        if ($s->type === 'RELEASE') {
            return 'RELEASE SAVEPOINT ' . $this->ident($s->name);
        }

        return 'SAVEPOINT ' . $this->ident($s->name);
    }

    private function renderAttach(AttachStatement $a): string
    {
        return 'ATTACH ' . $this->render($a->expression) . ' AS ' . $this->ident($a->schemaName);
    }

    private function renderDetach(DetachStatement $d): string
    {
        return 'DETACH ' . $this->ident($d->schemaName);
    }

    private function renderPragma(PragmaStatement $p): string
    {
        $sql = 'PRAGMA ';
        if ($p->schema !== null) {
            $sql .= $this->ident($p->schema) . '.';
        }
        $sql .= $this->ident($p->name);
        if ($p->value !== null) {
            $sql .= ' = ' . $this->render($p->value);
        }

        return $sql;
    }

    private function renderExplain(ExplainStatement $e): string
    {
        $sql = 'EXPLAIN ';
        if ($e->queryPlan) {
            $sql .= 'QUERY PLAN ';
        }

        return $sql . $this->render($e->statement);
    }

    private function renderLiteral(Literal $lit): string
    {
        return match ($lit->kind) {
            'null' => 'NULL',
            'string' => $this->stringLiteral((string) $lit->value),
            'int' => (string) $lit->value,
            'float' => var_export((float) $lit->value, true),
            default => var_export($lit->value, true),
        };
    }

    private function renderColumnReference(ColumnReference $ref): string
    {
        $name = $this->ident($ref->name);
        if ($ref->table !== null) {
            return $this->ident($ref->table) . '.' . $name;
        }

        return $name;
    }

    private function renderBinary(BinaryOperation $op): string
    {
        $parentPrec = self::PRECEDENCE[$op->operator] ?? 99;
        $assoc = in_array($op->operator, self::ASSOCIATIVE, true);

        $left = $this->render($op->left);
        $right = $this->render($op->right);

        if ($op->left instanceof BinaryOperation) {
            $leftPrec = self::PRECEDENCE[$op->left->operator] ?? 99;
            if ($leftPrec < $parentPrec) {
                $left = '(' . $left . ')';
            }
        }

        if ($op->right instanceof BinaryOperation) {
            $rightPrec = self::PRECEDENCE[$op->right->operator] ?? 99;
            if ($rightPrec < $parentPrec || ($rightPrec === $parentPrec && !$assoc)) {
                $right = '(' . $right . ')';
            }
        }

        return $left . ' ' . $op->operator . ' ' . $right;
    }

    private function renderUnary(UnaryOperation $op): string
    {
        $operand = $this->render($op->operand);
        if ($op->operand instanceof BinaryOperation) {
            $operand = '(' . $operand . ')';
        }

        if ($op->operator === 'NOT') {
            return 'NOT ' . $operand;
        }

        return $op->operator . $operand;
    }

    private function renderBetween(BetweenExpression $b): string
    {
        $sql = $this->render($b->expression) . ' ';
        if ($b->not) {
            $sql .= 'NOT ';
        }
        $sql .= 'BETWEEN ' . $this->render($b->lower) . ' AND ' . $this->render($b->upper);

        return $sql;
    }

    private function renderIn(InExpression $in): string
    {
        $sql = $this->render($in->expression) . ' ';
        if ($in->not) {
            $sql .= 'NOT ';
        }
        $sql .= 'IN (';
        if ($in->subquery !== null) {
            $sql .= $this->render($in->subquery);
        } else {
            $sql .= $this->renderList($in->list);
        }
        $sql .= ')';

        return $sql;
    }

    private function renderIsNull(IsNullExpression $i): string
    {
        $sql = $this->render($i->expression) . ' IS';
        if ($i->not) {
            $sql .= ' NOT';
        }

        return $sql . ' NULL';
    }

    private function renderLike(LikeExpression $l): string
    {
        $sql = $this->render($l->expression) . ' ';
        if ($l->not) {
            $sql .= 'NOT ';
        }
        $sql .= $l->glob ? 'GLOB ' : 'LIKE ';

        return $sql . $this->render($l->pattern);
    }

    private function renderCast(CastExpression $c): string
    {
        return 'CAST(' . $this->render($c->expression) . ' AS ' . $c->type . ')';
    }

    private function renderCase(CaseExpression $c): string
    {
        $sql = 'CASE';
        if ($c->subject !== null) {
            $sql .= ' ' . $this->render($c->subject);
        }
        foreach ($c->whens as $when) {
            $sql .= ' ' . $this->renderWhenClause($when);
        }
        if ($c->else !== null) {
            $sql .= ' ELSE ' . $this->render($c->else);
        }
        $sql .= ' END';

        return $sql;
    }

    private function renderWhenClause(WhenClause $w): string
    {
        return 'WHEN ' . $this->render($w->when) . ' THEN ' . $this->render($w->then);
    }

    private function renderFunctionCall(FunctionCall $fn): string
    {
        $name = $this->ident($fn->name);
        $args = $this->renderList($fn->arguments);
        if ($fn->distinct) {
            $args = 'DISTINCT ' . $args;
        }

        return $name . '(' . $args . ')';
    }

    private function renderWindowFunction(WindowFunction $wf): string
    {
        $sql = $this->renderFunctionCall($wf->function) . ' OVER ';
        if ($wf->specification !== null) {
            $sql .= '(' . $this->renderWindowSpec($wf->specification) . ')';
        } elseif ($wf->windowName !== null) {
            $sql .= $this->ident($wf->windowName);
        }

        return $sql;
    }

    private function renderWindowSpec(WindowSpecification $spec): string
    {
        $parts = [];
        if ($spec->partitionBy !== null) {
            $parts[] = 'PARTITION BY ' . $this->renderList($spec->partitionBy);
        }
        if ($spec->orderBy !== null) {
            $parts[] = 'ORDER BY ' . $this->renderList($spec->orderBy);
        }
        if ($spec->frame !== null) {
            $parts[] = $this->renderFrameSpec($spec->frame);
        }

        return implode(' ', $parts);
    }

    private function renderFrameSpec(FrameSpec $fs): string
    {
        $sql = $fs->unit . ' ';
        if ($fs->end !== null) {
            $sql .= 'BETWEEN ' . $this->renderFrameBound($fs->start)
                . ' AND ' . $this->renderFrameBound($fs->end);
        } else {
            $sql .= $this->renderFrameBound($fs->start);
        }

        return $sql;
    }

    private function renderFrameBound(FrameBound $fb): string
    {
        return match ($fb->type) {
            'UNBOUNDED_PRECEDING' => 'UNBOUNDED PRECEDING',
            'UNBOUNDED_FOLLOWING' => 'UNBOUNDED FOLLOWING',
            'CURRENT_ROW' => 'CURRENT ROW',
            'EXPR_PRECEDING' => ($fb->expression !== null ? $this->render($fb->expression) : '') . ' PRECEDING',
            'EXPR_FOLLOWING' => ($fb->expression !== null ? $this->render($fb->expression) : '') . ' FOLLOWING',
            default => $fb->type,
        };
    }

    private function renderAssignment(Assignment $a): string
    {
        $value = $a->value !== null ? $this->render($a->value) : 'NULL';

        return $this->ident($a->column) . ' = ' . $value;
    }

    private function renderOrderBy(OrderBy $o): string
    {
        if ($o->expression === null) {
            return '';
        }
        $sql = $this->render($o->expression);
        if ($o->desc) {
            $sql .= ' DESC';
        }

        return $sql;
    }

    private function renderResultColumn(ResultColumn $rc): string
    {
        $sql = $this->render($rc->expression);
        if ($rc->alias !== null) {
            $sql .= ' AS ' . $this->ident($rc->alias);
        }

        return $sql;
    }

    private function renderReturningClause(ReturningClause $r): string
    {
        return 'RETURNING ' . $this->renderList($r->columns);
    }

    private function renderWithClause(WithClause $w): string
    {
        $sql = 'WITH ';
        if ($w->recursive) {
            $sql .= 'RECURSIVE ';
        }
        $ctes = [];
        foreach ($w->ctes as $cte) {
            $ctes[] = $this->renderCte($cte);
        }

        return $sql . implode(', ', $ctes);
    }

    private function renderCte(CommonTableExpression $cte): string
    {
        $sql = $this->ident($cte->name);
        if ($cte->columns !== null) {
            $sql .= ' (' . $this->renderIdentList($cte->columns) . ')';
        }
        $sql .= ' AS (' . $this->render($cte->query) . ')';

        return $sql;
    }

    private function renderTableReference(TableReference $t): string
    {
        $sql = '';
        if ($t->name !== null) {
            $sql = $this->ident($t->name);
        } elseif ($t->subquery !== null) {
            $sql = '(' . $this->render($t->subquery) . ')';
        }

        if ($t->alias !== null) {
            $sql .= ' AS ' . $this->ident($t->alias);
        }
        if ($t->columnAliases !== null) {
            $sql .= ' (' . $this->renderIdentList($t->columnAliases) . ')';
        }

        return $sql;
    }

    private function renderJoinClause(JoinClause $j): string
    {
        $sql = '';
        if ($j->natural) {
            $sql .= 'NATURAL ';
        }
        if ($j->type !== '') {
            $sql .= $j->type . ' ';
        }
        $sql .= 'JOIN ' . $this->renderTableReference($j->table);

        if ($j->on !== null) {
            $sql .= ' ON ' . $this->render($j->on);
        } elseif ($j->using !== null) {
            $sql .= ' USING (' . $this->renderIdentList($j->using) . ')';
        }

        return $sql;
    }

    private function renderJoinConstraint(JoinConstraint $jc): string
    {
        if ($jc->on !== null) {
            return 'ON ' . $this->render($jc->on);
        }
        if ($jc->using !== null) {
            return 'USING (' . $this->renderIdentList($jc->using) . ')';
        }

        return '';
    }

    private function renderFromClause(FromClause $fc): string
    {
        $sql = $this->renderTableReference($fc->base);
        foreach ($fc->joins as $join) {
            $sql .= ' ' . $this->renderJoinClause($join);
        }

        return $sql;
    }

    private function renderColumnDefinition(ColumnDefinition $cd): string
    {
        $sql = $this->ident($cd->name);
        if ($cd->type !== null) {
            $sql .= ' ' . $cd->type;
        }
        foreach ($cd->constraints as $constraint) {
            $c = $this->renderColumnConstraint($constraint);
            if ($c !== '') {
                $sql .= ' ' . $c;
            }
        }

        return $sql;
    }

    private function renderColumnConstraint(ColumnConstraint $c): string
    {
        $conflict = $c->conflictAction !== null
            ? ' ON CONFLICT ' . str_replace('_', ' ', $c->conflictAction)
            : '';

        switch ($c->type) {
            case 'PRIMARY_KEY':
                $sql = 'PRIMARY KEY';
                if ($c->asc !== null) {
                    $sql .= $c->asc ? ' ASC' : ' DESC';
                }
                $sql .= $conflict;
                if ($c->autoincrement) {
                    $sql .= ' AUTOINCREMENT';
                }
                return $sql;

            case 'NOT_NULL':
                return 'NOT NULL' . $conflict;

            case 'UNIQUE':
                return 'UNIQUE' . $conflict;

            case 'CHECK':
                $expr = $c->expression !== null ? $this->render($c->expression) : '';
                return 'CHECK (' . $expr . ')';

            case 'DEFAULT':
                $val = $c->expression !== null
                    ? $this->render($c->expression)
                    : ($c->value ?? 'NULL');
                return 'DEFAULT ' . $val;

            case 'COLLATE':
                return 'COLLATE ' . ($c->value ?? '');

            case 'REFERENCES':
                return $c->foreignKey !== null ? $this->renderForeignKeyClause($c->foreignKey) : '';
        }

        return $c->type;
    }

    private function renderTableConstraint(TableConstraint $tc): string
    {
        $sql = '';
        if ($tc->name !== null) {
            $sql .= 'CONSTRAINT ' . $this->ident($tc->name) . ' ';
        }

        $cols = $tc->columns !== null
            ? ' (' . $this->renderIdentList($tc->columns) . ')'
            : '';

        $conflict = $tc->conflictAction !== null
            ? ' ON CONFLICT ' . str_replace('_', ' ', $tc->conflictAction)
            : '';

        switch ($tc->type) {
            case 'PRIMARY_KEY':
                return $sql . 'PRIMARY KEY' . $cols . $conflict;

            case 'UNIQUE':
                return $sql . 'UNIQUE' . $cols . $conflict;

            case 'CHECK':
                $expr = $tc->expression !== null ? $this->render($tc->expression) : '';
                return $sql . 'CHECK (' . $expr . ')';

            case 'FOREIGN_KEY':
                $sql .= 'FOREIGN KEY' . $cols;
                if ($tc->foreignKey !== null) {
                    $sql .= ' ' . $this->renderForeignKeyClause($tc->foreignKey);
                }
                return $sql;
        }

        return $sql . $tc->type;
    }

    private function renderForeignKeyClause(ForeignKeyClause $fk): string
    {
        $sql = 'REFERENCES ' . $this->ident($fk->table);
        if ($fk->columns !== null) {
            $sql .= ' (' . $this->renderIdentList($fk->columns) . ')';
        }
        if ($fk->onDelete !== null) {
            $sql .= ' ON DELETE ' . str_replace('_', ' ', $fk->onDelete);
        }
        if ($fk->onUpdate !== null) {
            $sql .= ' ON UPDATE ' . str_replace('_', ' ', $fk->onUpdate);
        }

        return $sql;
    }

    private function renderForeignKeyAction(ForeignKeyAction $fa): string
    {
        return 'ON ' . $fa->type . ' ' . str_replace('_', ' ', $fa->action);
    }

    private function renderTableOptions(TableOptions $to): string
    {
        $opts = [];
        if ($to->withoutRowid) {
            $opts[] = 'WITHOUT ROWID';
        }
        if ($to->strict) {
            $opts[] = 'STRICT';
        }

        return implode(', ', $opts);
    }

    private function renderUpsertClause(UpsertClause $u): string
    {
        $sql = 'ON CONFLICT';
        if ($u->conflictTarget !== null) {
            $sql .= ' (' . $this->renderIdentList($u->conflictTarget) . ')';
        }

        if ($u->assignments === null) {
            return $sql . ' DO NOTHING';
        }

        $sql .= ' DO UPDATE SET ' . $this->renderList($u->assignments);
        if ($u->where !== null) {
            $sql .= ' WHERE ' . $this->render($u->where);
        }

        return $sql;
    }

    private function renderInsertValuesBody(InsertValuesBody $body): string
    {
        $rows = [];
        foreach ($body->rows as $row) {
            $rows[] = '(' . $this->renderList($row) . ')';
        }

        return 'VALUES ' . implode(', ', $rows);
    }

    private function renderTriggerBody(TriggerBody $tb): string
    {
        $stmts = [];
        foreach ($tb->statements as $stmt) {
            $r = $this->render($stmt);
            if ($r !== '') {
                $stmts[] = $r;
            }
        }

        return 'BEGIN ' . implode('; ', $stmts) . '; END';
    }

    /**
     * @param array<array-key, Expression|Node> $items
     */
    private function renderList(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            $parts[] = $this->render($item);
        }

        return implode(', ', $parts);
    }

    /**
     * @param list<string> $items
     */
    private function renderIdentList(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            $parts[] = $this->ident($item);
        }

        return implode(', ', $parts);
    }

    private function stringLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function ident(string $name): string
    {
        $simple = preg_match('/^[\p{L}_]\w*$/u', $name) === 1;
        if ($simple && !in_array(strtoupper($name), self::RESERVED, true)) {
            return $name;
        }

        return '"' . str_replace('"', '""', $name) . '"';
    }
}
