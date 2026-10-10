<?php

namespace OfflineAgency\MongoAutoSync\Exceptions;

/**
 * The database context of a synchronisation cannot be established safely (unknown tenant, a connection that
 * now points to another database, a job without context while tenancy is configured): nothing is written.
 */
class SyncContextException extends MongoAutoSyncException
{
    //
}
