<?php declare(strict_types = 1);

namespace ShipMonk\PHPStan\DeadCode\Provider;

use Composer\InstalledVersions;
use ReflectionClass;
use ReflectionMethod;
use const PHP_VERSION_ID;

class DoctrineUsageProvider extends ReflectionBasedMemberUsageProvider
{

    private bool $enabled;

    public function __construct(?bool $enabled)
    {
        $this->enabled = $enabled ?? $this->isDoctrineInstalled();
    }

    public function shouldMarkMethodAsUsed(ReflectionMethod $method): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $methodName = $method->getName();
        $class = $method->getDeclaringClass();

        return $this->isEventSubscriberMethod($method)
            || $this->isLifecycleEventMethod($method)
            || $this->isEntityRepositoryConstructor($class, $method)
            || $this->isPartOfAsEntityListener($class, $methodName)
            || $this->isProbablyDoctrineListener($methodName);
    }

    protected function isEventSubscriberMethod(ReflectionMethod $method): bool
    {
        // this is simplification, we should deduce that from AST of getSubscribedEvents() method
        return $method->getDeclaringClass()->implementsInterface('Doctrine\Common\EventSubscriber');
    }

    protected function isLifecycleEventMethod(ReflectionMethod $method): bool
    {
        return $this->hasAttribute($method, 'Doctrine\ORM\Mapping\PostLoad')
            || $this->hasAttribute($method, 'Doctrine\ORM\Mapping\PostPersist')
            || $this->hasAttribute($method, 'Doctrine\ORM\Mapping\PostUpdate')
            || $this->hasAttribute($method, 'Doctrine\ORM\Mapping\PreFlush')
            || $this->hasAttribute($method, 'Doctrine\ORM\Mapping\PrePersist')
            || $this->hasAttribute($method, 'Doctrine\ORM\Mapping\PreRemove')
            || $this->hasAttribute($method, 'Doctrine\ORM\Mapping\PreUpdate');
    }

    /**
     * Ideally, we would need to parse DIC xml to know this for sure just like phpstan-symfony does.
     * - see Doctrine\ORM\Events::*
     */
    protected function isProbablyDoctrineListener(string $methodName): bool
    {
        return $methodName === 'preRemove'
            || $methodName === 'postRemove'
            || $methodName === 'prePersist'
            || $methodName === 'postPersist'
            || $methodName === 'preUpdate'
            || $methodName === 'postUpdate'
            || $methodName === 'postLoad'
            || $methodName === 'loadClassMetadata'
            || $methodName === 'onClassMetadataNotFound'
            || $methodName === 'preFlush'
            || $methodName === 'onFlush'
            || $methodName === 'postFlush'
            || $methodName === 'onClear';
    }

    protected function hasAttribute(ReflectionMethod $method, string $attributeClass): bool
    {
        if (PHP_VERSION_ID < 8_00_00) {
            return false;
        }

        return $method->getAttributes($attributeClass) !== [];
    }

    /**
     * @param ReflectionClass<object> $class
     */
    protected function isPartOfAsEntityListener(ReflectionClass $class, string $methodName): bool
    {
        if (PHP_VERSION_ID < 8_00_00) {
            return false;
        }

        foreach ($class->getAttributes('Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener') as $attribute) {
            $listenerMethodName = $attribute->getArguments()['method'] ?? $attribute->getArguments()[1] ?? null;

            if ($listenerMethodName === $methodName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    protected function isEntityRepositoryConstructor(ReflectionClass $class, ReflectionMethod $method): bool
    {
        if (!$method->isConstructor()) {
            return false;
        }

        return $class->isSubclassOf('Doctrine\ORM\EntityRepository');
    }

    private function isDoctrineInstalled(): bool
    {
        return InstalledVersions::isInstalled('doctrine/orm')
            || InstalledVersions::isInstalled('doctrine/event-manager')
            || InstalledVersions::isInstalled('doctrine/doctrine-bundle');
    }

}
