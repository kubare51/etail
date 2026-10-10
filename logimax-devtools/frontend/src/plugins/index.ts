/**
 * Plugins Registration
 */
import { PluginRegistry } from '@core/plugin-system';
import { lcaPlugin } from './lca';
import { testingPlugin } from './testing';
import { schemaPlugin } from './schema';
import { queryPlugin } from './query';
import { logsPlugin } from './logs';

PluginRegistry.register(lcaPlugin);
PluginRegistry.register(schemaPlugin);
PluginRegistry.register(queryPlugin);
PluginRegistry.register(testingPlugin);
PluginRegistry.register(logsPlugin);

export { lcaPlugin, schemaPlugin, queryPlugin, testingPlugin, logsPlugin };
