<?php

namespace OfflineAgency\MongoAutoSync\Exceptions;

/**
 * The stored state of a referenced relationship does not allow the requested write (a child changed owner
 * while it was being written, or a HasOne holds several documents): nothing is written for that child.
 */
class ReferencedConflictException extends MongoAutoSyncException
{
    //
}
