/**
 * Shell — App layout with premium sidebar
 * Light-first design, Stripe/Linear-inspired
 */

import { useState, useEffect } from 'react';
import { Outlet, NavLink, useLocation } from 'react-router-dom';
import { usePlugins } from '@core/plugin-system';
import { cn } from '@/lib/utils';
import {
  LayoutDashboard,
  Settings,
  ChevronLeft,
  ChevronRight,
  Search,
  Sun,
  Moon,
  Zap,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useGlobalShortcuts } from '@/hooks/useGlobalShortcuts';

export default function Shell() {
  const plugins = usePlugins();
  const location = useLocation();
  const [collapsed, setCollapsed] = useState(false);
  const [isDark, setIsDark] = useState(false);

  // Enable Global Keyboard Shortcuts
  useGlobalShortcuts();

  // Theme toggle
  useEffect(() => {
    const saved = localStorage.getItem('devtools-theme');
    if (saved === 'dark') {
      document.documentElement.classList.add('dark');
      setIsDark(true);
    }
  }, []);

  const toggleTheme = () => {
    const next = !isDark;
    setIsDark(next);
    if (next) {
      document.documentElement.classList.add('dark');
      localStorage.setItem('devtools-theme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      localStorage.setItem('devtools-theme', 'light');
    }
  };

  // Determine if a path is active (including sub-routes)
  const isPathActive = (path: string) => {
    if (path === '/') return location.pathname === '/';
    return location.pathname.startsWith(path);
  };

  return (
    <div className="flex h-screen bg-background overflow-hidden">
      {/* ── Sidebar ── */}
      <aside
        className={cn(
          'flex flex-col border-r transition-all duration-300 ease-in-out relative',
          'bg-[hsl(var(--sidebar-bg))] border-[hsl(var(--sidebar-border))]',
          collapsed ? 'w-[60px]' : 'w-[240px]'
        )}
      >
        {/* Logo */}
        <div className={cn(
          'h-14 flex items-center border-b border-[hsl(var(--sidebar-border))] px-4',
          collapsed ? 'justify-center' : 'gap-3'
        )}>
          <div className="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shrink-0">
            <Zap className="w-4 h-4" />
          </div>
          <span className={cn(
            'font-bold text-base tracking-tight transition-all duration-300 overflow-hidden whitespace-nowrap',
            collapsed ? 'w-0 opacity-0' : 'opacity-100'
          )}>
            DevTools
          </span>
        </div>

        {/* Collapse Toggle */}
        <Button
          variant="outline"
          size="icon"
          className="absolute -right-3 top-[52px] h-6 w-6 rounded-full bg-background shadow-md z-10 border"
          onClick={() => setCollapsed(!collapsed)}
        >
          {collapsed ? <ChevronRight className="w-3 h-3" /> : <ChevronLeft className="w-3 h-3" />}
        </Button>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto scrollbar-none py-4 px-2 flex flex-col gap-0.5">
          {/* Home link */}
          <NavLink
            to="/"
            end
            className={() => cn(
              'nav-item',
              isPathActive('/') && location.pathname === '/' && 'active'
            )}
            title={collapsed ? 'Home' : undefined}
          >
            <LayoutDashboard className="w-4 h-4 shrink-0" />
            <span className={cn(
              'truncate transition-all duration-300',
              collapsed ? 'w-0 opacity-0' : 'opacity-100'
            )}>
              Home
            </span>
          </NavLink>

          {/* Divider */}
          {!collapsed && (
            <div className="text-[10px] font-semibold tracking-widest text-muted-foreground/60 uppercase px-3 mt-4 mb-1">
              Tools
            </div>
          )}
          {collapsed && <div className="my-2 mx-2 border-t border-[hsl(var(--sidebar-border))]" />}

          {/* Plugin nav items */}
          {plugins.map((plugin) => (
            <NavLink
              key={plugin.id}
              to={`/${plugin.id}`}
              className={() => cn(
                'nav-item',
                isPathActive(`/${plugin.id}`) && 'active'
              )}
              title={collapsed ? plugin.name : undefined}
            >
              <plugin.icon className="w-4 h-4 shrink-0" />
              <span className={cn(
                'truncate transition-all duration-300',
                collapsed ? 'w-0 opacity-0' : 'opacity-100'
              )}>
                {plugin.name}
              </span>
            </NavLink>
          ))}
        </nav>

        {/* Bottom section */}
        <div className="border-t border-[hsl(var(--sidebar-border))] p-2 flex flex-col gap-0.5">
          {/* Theme toggle */}
          <button
            onClick={toggleTheme}
            className="nav-item"
            title={collapsed ? (isDark ? 'Light Mode' : 'Dark Mode') : undefined}
          >
            {isDark ? <Sun className="w-4 h-4 shrink-0" /> : <Moon className="w-4 h-4 shrink-0" />}
            <span className={cn(
              'truncate transition-all duration-300',
              collapsed ? 'w-0 opacity-0' : 'opacity-100'
            )}>
              {isDark ? 'Light Mode' : 'Dark Mode'}
            </span>
          </button>

          {/* Settings */}
          <NavLink
            to="/settings"
            className={() => cn(
              'nav-item',
              isPathActive('/settings') && 'active'
            )}
            title={collapsed ? 'Settings' : undefined}
          >
            <Settings className="w-4 h-4 shrink-0" />
            <span className={cn(
              'truncate transition-all duration-300',
              collapsed ? 'w-0 opacity-0' : 'opacity-100'
            )}>
              Settings
            </span>
          </NavLink>

          {/* Version */}
          {!collapsed && (
            <div className="text-[10px] text-muted-foreground/50 text-center mt-1 mb-0.5">
              v1.2.0
            </div>
          )}
        </div>
      </aside>

      {/* ── Main Content ── */}
      <main className="flex-1 flex flex-col min-w-0 overflow-hidden">
        {/* Top bar */}
        <header className="h-14 flex items-center justify-between px-6 border-b bg-card/50 backdrop-blur-sm shrink-0">
          {/* Breadcrumb area */}
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <span className="font-medium text-foreground">
              {location.pathname === '/' ? 'Dashboard' : 
                plugins.find(p => isPathActive(`/${p.id}`))?.name || 'DevTools'}
            </span>
          </div>

          {/* Right side */}
          <div className="flex items-center gap-3">
            {/* Search trigger */}
            <button 
              className="flex items-center gap-2 px-3 py-1.5 text-sm text-muted-foreground bg-muted/50 hover:bg-muted rounded-lg border transition-colors"
              onClick={() => {
                const evt = new KeyboardEvent('keydown', { key: 'k', ctrlKey: true });
                document.dispatchEvent(evt);
              }}
            >
              <Search className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Search</span>
              <kbd className="kbd ml-1">⌘K</kbd>
            </button>

            {/* Status */}
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
              <span className="status-dot online" />
              <span className="hidden md:inline">Connected</span>
            </div>
          </div>
        </header>

        {/* Content */}
        <div className="flex-1 overflow-auto scrollbar-thin">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
