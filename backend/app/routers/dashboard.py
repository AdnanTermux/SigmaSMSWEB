from datetime import datetime, timedelta

from fastapi import APIRouter, Depends, Query
from sqlalchemy import text
from sqlalchemy.orm import Session

from app.db.session import get_db

router = APIRouter(prefix="/ajax", tags=["dashboard"])


def _days_for_range(range_key: str) -> int:
    return {"24h": 1, "7d": 7, "30d": 30, "90d": 90}.get(range_key, 7)


@router.get("/dashboard_stats.php")
def dashboard_stats(
    range_key: str = Query("7d", alias="range"),
    recent: int | None = Query(None),
    db: Session = Depends(get_db),
):
    days = _days_for_range(range_key)
    start = (datetime.utcnow() - timedelta(days=days - 1)).strftime("%Y-%m-%d 00:00:00")
    end = datetime.utcnow().strftime("%Y-%m-%d 23:59:59")

    stats = {}
    stats["today_sms"] = int(db.execute(text("SELECT COUNT(*) FROM sms_received WHERE DATE(received_at)=CURDATE()")).scalar() or 0)
    stats["week_sms"] = int(db.execute(text("SELECT COUNT(*) FROM sms_received WHERE received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")).scalar() or 0)
    stats["today_profit"] = f"{float(db.execute(text('SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE DATE(created_at)=CURDATE()')).scalar() or 0):.6f}"
    stats["week_profit"] = f"{float(db.execute(text('SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')).scalar() or 0):.6f}"
    stats["total_numbers"] = int(db.execute(text("SELECT COUNT(*) FROM numbers WHERE status='active'")).scalar() or 0)
    stats["total_users"] = int(db.execute(text("SELECT COUNT(*) FROM users WHERE status='active'")).scalar() or 0)
    stats["range_sms"] = int(db.execute(text("SELECT COUNT(*) FROM sms_received WHERE received_at BETWEEN :s AND :e"), {"s": start, "e": end}).scalar() or 0)
    stats["range_profit"] = f"{float(db.execute(text('SELECT COALESCE(SUM(profit_amount),0) FROM profit_log WHERE created_at BETWEEN :s AND :e'), {'s': start, 'e': end}).scalar() or 0):.6f}"

    payload = {"status": "success", "data": stats}
    if recent:
        rows = db.execute(
            text(
                "SELECT sr.*, pl.profit_amount AS profit "
                "FROM sms_received sr LEFT JOIN profit_log pl ON pl.sms_received_id = sr.id "
                "ORDER BY sr.received_at DESC LIMIT 10"
            )
        ).mappings().all()
        payload["recent"] = [dict(r) for r in rows]
    return payload


@router.get("/dashboard_charts.php")
def dashboard_charts(
    type: str = Query("sms"),
    range_key: str = Query("7d", alias="range"),
    db: Session = Depends(get_db),
):
    days = _days_for_range(range_key)
    if type == "sms":
        rows = db.execute(
            text(
                "SELECT DATE(received_at) AS day, COUNT(*) AS cnt "
                "FROM sms_received "
                "WHERE received_at >= DATE_SUB(CURDATE(), INTERVAL :d DAY) "
                "GROUP BY DATE(received_at) ORDER BY day ASC"
            ),
            {"d": days - 1},
        ).mappings().all()
        by_day = {r["day"].strftime("%Y-%m-%d"): int(r["cnt"]) for r in rows if r["day"]}
        categories = []
        data = []
        for i in range(days - 1, -1, -1):
            d = (datetime.utcnow() - timedelta(days=i)).strftime("%Y-%m-%d")
            categories.append(datetime.strptime(d, "%Y-%m-%d").strftime("%b %d"))
            data.append(by_day.get(d, 0))
        return {"categories": categories, "data": data}

    if type == "services":
        rows = db.execute(
            text(
                "SELECT service, COUNT(*) AS cnt FROM sms_received "
                "WHERE service IS NOT NULL AND service != '' "
                "GROUP BY service ORDER BY cnt DESC LIMIT 5"
            )
        ).mappings().all()
        return {"labels": [r["service"].title() for r in rows], "data": [int(r["cnt"]) for r in rows]}

    if type == "countries":
        rows = db.execute(
            text(
                "SELECT country, COUNT(*) AS cnt FROM sms_received "
                "WHERE country IS NOT NULL AND country != '' "
                "GROUP BY country ORDER BY cnt DESC LIMIT 7"
            )
        ).mappings().all()
        return {"labels": [str(r["country"]).upper() for r in rows], "data": [int(r["cnt"]) for r in rows]}

    return {"labels": [], "data": []}
