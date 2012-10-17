<?php

namespace FSC\HateoasBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class FSCHateoasExtension extends ConfigurableExtension
{
    public function loadInternal(array $config, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        foreach (array('services.yml', 'metadata.yml') as $file) {
            $loader->load($file);
        }

        $container
            ->getDefinition('fsc_hateoas.serializer.handler.pagerfanta')
            ->replaceArgument(1, $config['pagerfanta']['xml_elements_names_use_serializer_metadata'])
        ;

        $container
            ->getDefinition('fsc_hateoas.factory.pager_link')
            ->replaceArgument(1, $config['pagerfanta']['links']['page_parameter_name'])
            ->replaceArgument(2, $config['pagerfanta']['links']['limit_parameter_name'])
        ;

        $this->configureMetadata($config, $container);
    }

    protected function configureMetadata(array $config, ContainerBuilder $container)
    {
        // The following configuration has been copied from JMS\SerializerBundle\DependencyInjection\JMSSerializerExtension

        if ('none' === $config['metadata']['cache']) {
            $container->removeAlias('fsc_hateoas.metadata.cache');
        } elseif ('file' === $config['metadata']['cache']) {
            $container
                ->getDefinition('fsc_hateoas.metadata.cache.file')
                ->replaceArgument(0, $config['metadata']['file_cache']['dir'])
            ;

            $dir = $container->getParameterBag()->resolveValue($config['metadata']['file_cache']['dir']);
            if (!file_exists($dir) && (!$rs = @mkdir($dir, 0777, true))) {
                throw new \RuntimeException(sprintf('Could not create cache directory "%s".', $dir));
            }
        } else {
            $container->setAlias('fsc_hateoas.metadata.cache', new Alias($config['metadata']['cache'], false));
        }

        $container
            ->getDefinition('fsc_hateoas.metadata.factory')
            ->replaceArgument(2, $config['metadata']['debug'])
        ;

        // directories
        $directories = array();
        $bundles = $container->getParameter('kernel.bundles');
        if ($config['metadata']['auto_detection']) {
            foreach ($bundles as $name => $class) {
                $ref = new \ReflectionClass($class);

                $directories[$ref->getNamespaceName()] = dirname($ref->getFileName()).'/Resources/config/hateoas';
            }
        }
        foreach ($config['metadata']['directories'] as $directory) {
            $directory['path'] = rtrim(str_replace('\\', '/', $directory['path']), '/');

            if ('@' === $directory['path'][0]) {
                $bundleName = substr($directory['path'], 1, strpos($directory['path'], '/') - 1);

                if (!isset($bundles[$bundleName])) {
                    throw new \RuntimeException(sprintf('The bundle "%s" has not been registered with AppKernel. Available bundles: %s', $bundleName, implode(', ', array_keys($bundles))));
                }

                $ref = new \ReflectionClass($bundles[$bundleName]);
                $directory['path'] = dirname($ref->getFileName()).substr($directory['path'], strlen('@'.$bundleName));
            }

            $directories[rtrim($directory['namespace_prefix'], '\\')] = rtrim($directory['path'], '\\/');
        }
        $container
            ->getDefinition('fsc_hateoas.metadata.file_locator')
            ->replaceArgument(0, $directories)
        ;
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration();
    }

    public function getAlias()
    {
        return 'fsc_hateoas';
    }
}
