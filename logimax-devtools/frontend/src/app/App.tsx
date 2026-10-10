/**
 * App Component — Main application with premium homepage
 * Light-first design, Stripe/Linear-inspired
 */

import { useRoutes, Link } from 'react-router-dom';
import { usePlugins } from '@core/plugin-system';
import { CommandPalette } from '@/components/CommandPalette';
import Shell from './Shell';
import {
  Search,
  Table2,
  Terminal,
  Play,
  ScrollText,
  GitBranch,
  Activity,
  Database,
  FlaskConical,
  Clock,
  ArrowRight,
  Zap,
  ChevronRight,
  BarChart3,
} from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

// Create routes from plugins
function useAppRoutes() {
  const plugins = usePlugins();

  const routes = [
    {
      path: '/',
      element: <Shell />,
      children: [
        { index: true, element: <HomePage /> },
        ...plugins.flatMap((plugin) => plugin.routes),
        { path: '*', element: <NotFoundPage /> },
      ],
    },
  ];

  return useRoutes(routes);
}

// ─── Stat Cards Data ───
const stats = [
  {
    label: 'Functions Indexed',
    value: '12,847',
    description: 'across 438 files',
    icon: GitBranch,
    color: 'text-indigo-600 dark:text-indigo-400',
    bg: 'bg-indigo-50 dark:bg-indigo-950/30',
  },
  {
    label: 'Database Tables',
    value: '156',
    description: 'ret_* schema tracked',
    icon: Database,
    color: 'text-emerald-600 dark:text-emerald-400',
    bg: 'bg-emerald-50 dark:bg-emerald-950/30',
  },
  {
    label: 'Test Suites',
    value: '3',
    description: 'PHPUnit, Jest, pytest',
    icon: FlaskConical,
    color: 'text-amber-600 dark:text-amber-400',
    bg: 'bg-amber-50 dark:bg-amber-950/30',
  },
  {
    label: 'API Health',
    value: '99.2%',
    description: 'avg 45ms response',
    icon: Activity,
    color: 'text-violet-600 dark:text-violet-400',
    bg: 'bg-violet-50 dark:bg-violet-950/30',
  },
];

// ─── Quick Actions Data ───
const quickActions = [
  {
    title: 'Search Functions',
    description: 'Find functions across the codebase',
    icon: Search,
    href: '/lca/search',
    color: 'from-indigo-500/10 to-indigo-500/5',
    iconColor: 'text-indigo-600 dark:text-indigo-400',
  },
  {
    title: 'Explore Schema',
    description: 'Browse tables, columns & relationships',
    icon: Table2,
    href: '/schema',
    color: 'from-emerald-500/10 to-emerald-500/5',
    iconColor: 'text-emerald-600 dark:text-emerald-400',
  },
  {
    title: 'Run Query',
    description: 'Execute SQL in the sandbox',
    icon: Terminal,
    href: '/query',
    color: 'from-amber-500/10 to-amber-500/5',
    iconColor: 'text-amber-600 dark:text-amber-400',
  },
  {
    title: 'Run Tests',
    description: 'Execute test suites',
    icon: Play,
    href: '/testing',
    color: 'from-green-500/10 to-green-500/5',
    iconColor: 'text-green-600 dark:text-green-400',
  },
  {
    title: 'View Logs',
    description: 'Browse application logs',
    icon: ScrollText,
    href: '/logs',
    color: 'from-blue-500/10 to-blue-500/5',
    iconColor: 'text-blue-600 dark:text-blue-400',
  },
  {
    title: 'Impact Analysis',
    description: 'Trace function dependencies',
    icon: GitBranch,
    href: '/lca/search',
    color: 'from-violet-500/10 to-violet-500/5',
    iconColor: 'text-violet-600 dark:text-violet-400',
  },
];

// ─── Recent Activity Data (mock) ───
const recentActivity = [
  { action: 'Indexed etail-admin', detail: '12,847 functions parsed', time: '2h ago', icon: BarChart3, status: 'success' as const },
  { action: 'Query executed', detail: 'SELECT * FROM ret_billing LIMIT 5', time: '4h ago', icon: Terminal, status: 'info' as const },
  { action: 'PHPUnit tests', detail: '42 passed, 0 failed', time: 'Yesterday', icon: FlaskConical, status: 'success' as const },
  { action: 'Schema snapshot', detail: '156 tables cached', time: 'Yesterday', icon: Database, status: 'info' as const },
  { action: 'Impact analysis', detail: 'populateBillRowFromPacket', time: '2 days ago', icon: GitBranch, status: 'warning' as const },
];

const statusColors = {
  success: 'bg-emerald-500',
  info: 'bg-indigo-500',
  warning: 'bg-amber-500',
  error: 'bg-red-500',
};

// ═══════════════════════════════════════════
//   HOME PAGE
// ═══════════════════════════════════════════
function HomePage() {
  return (
    <div className="p-6 lg:p-8 max-w-[1400px] mx-auto space-y-8">
      {/* ── Welcome Header ── */}
      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <Zap className="w-5 h-5 text-indigo-500" />
            <h1 className="text-2xl font-bold tracking-tight">
              <span className="text-gradient">DevTools</span>
            </h1>
          </div>
          <p className="text-muted-foreground text-sm">
            Logimax ERP Development Environment
          </p>
        </div>
        <div className="hidden sm:flex items-center gap-2 text-xs text-muted-foreground">
          <span className="status-dot online" />
          Backend connected
        </div>
      </div>

      {/* ── Stats Row ── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {stats.map((stat, i) => (
          <Card
            key={stat.label}
            className="metric-card card-hover animate-fade-in-up"
            style={{ animationDelay: `${i * 60}ms` }}
          >
            <CardContent className="p-5">
              <div className="flex items-start justify-between mb-3">
                <div className={`p-2 rounded-lg ${stat.bg}`}>
                  <stat.icon className={`w-4 h-4 ${stat.color}`} />
                </div>
              </div>
              <div className="text-2xl font-bold tracking-tight">{stat.value}</div>
              <div className="text-xs text-muted-foreground mt-0.5">{stat.label}</div>
              <div className="text-[10px] text-muted-foreground/60 mt-0.5">{stat.description}</div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* ── Quick Actions ── */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-sm font-semibold tracking-tight text-foreground">Quick Actions</h2>
        </div>
        <div className="grid grid-cols-2 lg:grid-cols-3 gap-3">
          {quickActions.map((action, i) => (
            <Link
              key={action.title}
              to={action.href}
              className={`group relative flex items-start gap-4 p-4 rounded-xl border bg-card 
                card-hover animate-fade-in-up`}
              style={{ animationDelay: `${200 + i * 50}ms` }}
            >
              <div className={`p-2.5 rounded-lg bg-gradient-to-br ${action.color} shrink-0`}>
                <action.icon className={`w-4 h-4 ${action.iconColor}`} />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-1.5">
                  <span className="font-medium text-sm">{action.title}</span>
                  <ChevronRight className="w-3 h-3 text-muted-foreground/40 group-hover:text-primary group-hover:translate-x-0.5 transition-all" />
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">{action.description}</p>
              </div>
            </Link>
          ))}
        </div>
      </div>

      {/* ── Recent Activity ── */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-sm font-semibold tracking-tight text-foreground">Recent Activity</h2>
          <button className="text-xs text-muted-foreground hover:text-foreground transition-colors flex items-center gap-1">
            View all <ArrowRight className="w-3 h-3" />
          </button>
        </div>
        <Card>
          <CardContent className="p-0">
            <div className="divide-y divide-border">
              {recentActivity.map((item, i) => (
                <div
                  key={i}
                  className="flex items-center gap-4 px-5 py-3.5 hover:bg-muted/30 transition-colors animate-fade-in-up"
                  style={{ animationDelay: `${400 + i * 40}ms` }}
                >
                  {/* Timeline dot */}
                  <div className="relative flex items-center justify-center">
                    <div className={`w-2 h-2 rounded-full ${statusColors[item.status]}`} />
                  </div>
                  {/* Icon */}
                  <div className="p-1.5 rounded-md bg-muted/50">
                    <item.icon className="w-3.5 h-3.5 text-muted-foreground" />
                  </div>
                  {/* Content */}
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-medium">{item.action}</div>
                    <div className="text-xs text-muted-foreground truncate font-mono">{item.detail}</div>
                  </div>
                  {/* Time */}
                  <div className="flex items-center gap-1 text-xs text-muted-foreground/60 shrink-0">
                    <Clock className="w-3 h-3" />
                    {item.time}
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* ── Keyboard Shortcuts Hint ── */}
      <div className="flex items-center justify-center gap-6 text-xs text-muted-foreground/50 pt-2">
        <span><kbd className="kbd">⌘K</kbd> Command Palette</span>
        <span><kbd className="kbd">G</kbd> then <kbd className="kbd">S</kbd> Search</span>
        <span><kbd className="kbd">G</kbd> then <kbd className="kbd">D</kbd> Dashboard</span>
      </div>
    </div>
  );
}

// ─── 404 Page ───
function NotFoundPage() {
  return (
    <div className="flex flex-col items-center justify-center h-full gap-4">
      <div className="text-6xl font-bold text-muted-foreground/20">404</div>
      <p className="text-muted-foreground">Page not found</p>
      <Link
        to="/"
        className="text-sm text-primary hover:underline flex items-center gap-1"
      >
        <ArrowRight className="w-3 h-3 rotate-180" />
        Back to Dashboard
      </Link>
    </div>
  );
}

// ─── App Root ───
export default function App() {
  const routes = useAppRoutes();

  return (
    <>
      <CommandPalette />
      {routes}
    </>
  );
}
