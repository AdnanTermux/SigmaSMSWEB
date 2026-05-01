from sqlalchemy import text
from sqlalchemy.orm import Session

from app.db.session import SessionLocal


def run() -> None:
    db: Session = SessionLocal()
    try:
        # Placeholder worker task for Railway cron service.
        # This can be extended to fetch external OTP API and insert records.
        db.execute(text("SELECT 1"))
        db.commit()
        print("worker_ok")
    finally:
        db.close()


if __name__ == "__main__":
    run()
