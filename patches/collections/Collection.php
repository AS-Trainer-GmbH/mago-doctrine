<?php

declare(strict_types=1);

namespace Doctrine\Common\Collections;

use ArrayAccess;

/**
 * Patch shipped with gimmickmedia/mago-doctrine (`allCollectionsSelectable`): every Doctrine collection
 * used by the ORM (`ArrayCollection`, `PersistentCollection`, `LazyCriteriaCollection`) is selectable.
 *
 * @template TKey of array-key
 * @template T
 * @template-extends ReadableCollection<TKey, T>
 * @template-extends ArrayAccess<TKey, T>
 *
 * @method ReadableCollection<TKey, T>&Selectable<TKey, T> matching(Criteria $criteria)
 */
interface Collection extends ReadableCollection, ArrayAccess
{
}
