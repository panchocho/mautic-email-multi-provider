<?php

declare(strict_types=1);

namespace MauticPlugin\SmartMailerRouterBundle\Domain\Repository;

interface BaseRepositoryInterface
{
    public function save(object $entity, bool $flush = true): void;

    public function remove(object $entity, bool $flush = true): void;
}

