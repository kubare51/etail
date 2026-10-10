"""
Logimax DevTools - Backend Configuration
"""
from functools import lru_cache
from pathlib import Path
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Application settings"""
    
    # App
    app_name: str = "Logimax DevTools API"
    app_version: str = "1.0.0"
    debug: bool = True
    
    # Server
    host: str = "0.0.0.0"
    port: int = 8800
    
    # CORS
    cors_origins: list[str] = ["http://localhost:5173", "http://127.0.0.1:5173"]

    # API versioning — prefix for all plugin routes (e.g., /api/v1/lca/...)
    api_version: str = "v1"
    
    # Paths
    etail_root: Path = Path(r"c:\xampp\htdocs\etail_v3")
    logs_path: Path = Path(r"c:\xampp\htdocs\etail_v3\admin\log")
    
    # Database (for settings persistence)
    database_url: str = "sqlite+aiosqlite:///./devtools.db"

    # Auth — set DEVTOOLS_API_KEY to enable. Empty = auth disabled (dev mode).
    api_key: str = ""
    
    class Config:
        env_prefix = "DEVTOOLS_"
        env_file = ".env"
        extra = "ignore"


@lru_cache
def get_settings() -> Settings:
    """Get cached settings instance"""
    return Settings()
