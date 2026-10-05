<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Texts;

use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;
use Systopic\System\Tables\Nodes\Model as Node;

/**
 * One translated text field of one node — `cms_texts`.
 *
 * **Why the class is not called `Text`.** `Db\Type\Text` already is, and that
 * one is a column type used all over the models. Two classes one import apart
 * that mean entirely different things is a trap nobody needs; the class is
 * named after what it is a text *of*.
 *
 * The row is a key of three: node, language, field name. Where `Page` holds
 * nine fixed `text01..09` columns, this is the open version of the same idea —
 * any field name, any language.
 *
 * Note `text_id` is **not** auto-increment in the database, unlike every other
 * table here. The declaration says what this layer requires; the audit will
 * report the difference rather than hide it.
 */
final class Model extends TableRow
{
    public const TABLE  = 'texts';
    public const PREFIX = 'text';

    public ?Node $node {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** ISO code of the language, '*' for language-neutral. */
    public string $languageIso = '*' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Which field of the template this is the text for. */
    public string $fieldname = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public Text $content {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
