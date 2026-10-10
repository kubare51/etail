"""
Logimax DevTools - FastAPI Backend
Main application entry point
"""
import logging
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from .config import get_settings
from .core.logging import setup_logging
from .core.middleware import RequestTraceMiddleware
from .core.error_handlers import register_exception_handlers
from .core.plugin_system import plugin_registry

# Import and register plugins
from .plugins.lca import LcaPlugin
from .plugins.testing import TestingPlugin
from .plugins.logs import LogsPlugin

plugin_registry.register(LcaPlugin())
plugin_registry.register(TestingPlugin())
plugin_registry.register(LogsPlugin())

logger = logging.getLogger("devtools.app")


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan handler"""
    # Startup
    logger.info("Starting Logimax DevTools v%s", get_settings().app_version)
    await plugin_registry.init_all()
    plugins = [p.id for p in plugin_registry.get_all()]
    logger.info("Plugins loaded: %s", ", ".join(plugins))
    yield
    # Shutdown
    logger.info("Shutting down...")
    await plugin_registry.shutdown_all()
    logger.info("Shutdown complete")


def create_app() -> FastAPI:
    """Create and configure FastAPI application"""
    settings = get_settings()

    # Initialize logging before anything else
    setup_logging(debug=settings.debug)

    app = FastAPI(
        title=settings.app_name,
        version=settings.app_version,
        description=(
            "Internal tooling API for Logimax ERP codebase analysis, "
            "development pipelines, and real-time monitoring.\n\n"
            "### Features\n"
            "- **Code Analysis** — AST-based indexing, impact analysis, caller/callee graphs\n"
            "- **Pipeline** — 10-stage dev workflow from analysis to merge\n"
            "- **WebSocket** — Live streaming for index progress and pipeline events\n"
            "- **Git** — Branch info, diffs, changed files\n"
            "- **Auth** — API key protection on destructive endpoints "
            "(set `DEVTOOLS_API_KEY` env var)\n"
        ),
        contact={
            "name": "Logimax Technologies",
            "url": "https://github.com/Logimax-Technologies",
        },
        license_info={
            "name": "Proprietary",
            "identifier": "LicenseRef-scancode-proprietary-license",
        },
        openapi_tags=[
            {"name": "LCA — Index", "description": "Create, list, delete, and monitor code indexes"},
            {"name": "LCA — Search", "description": "Search functions, callers, callees across indexed code"},
            {"name": "LCA — Impact", "description": "Impact analysis and dependency graphs"},
            {"name": "LCA — Workflow", "description": "Standard dev workflow automation"},
            {"name": "LCA — Pipeline", "description": "10-stage development pipeline (create → merge)"},
            {"name": "LCA — Git", "description": "Git branch, diff, and changed file queries"},
            {"name": "LCA — Analytics", "description": "Codebase analytics and statistics"},
            {"name": "LCA — Tools", "description": "Utility tools (test generation, etc.)"},
            {"name": "LCA — WebSocket", "description": "Real-time streaming endpoints (index progress, pipeline events)"},
            {"name": "Testing", "description": "Test runner integration (PHPUnit, Jest)"},
            {"name": "Logs", "description": "Browse, search, and tail etail_v3 application logs"},
        ],
        lifespan=lifespan,
    )

    # Request trace middleware (must be added before CORS)
    app.add_middleware(RequestTraceMiddleware)

    # CORS
    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.cors_origins,
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

    # Health check
    @app.get("/health")
    async def health_check():
        from .core.websocket_manager import ws_manager
        return {
            "status": "healthy",
            "version": settings.app_version,
            "websocket": {
                "active_channels": ws_manager.active_channels,
                "total_connections": ws_manager.total_connections,
            },
        }

    # Register plugin routers
    for prefix, router in plugin_registry.get_all_routers():
        app.include_router(router, prefix=prefix)

    # Global exception handlers (must be registered after routers)
    register_exception_handlers(app)

    return app


app = create_app()


if __name__ == "__main__":
    import uvicorn
    settings = get_settings()
    uvicorn.run(
        "app.main:app",
        host=settings.host,
        port=settings.port,
        reload=settings.debug,
    )
