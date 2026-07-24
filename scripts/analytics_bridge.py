#!/usr/bin/env python3
"""
Analytics Bridge — Python-side entry point for PHP ↔ Python communication.

Reads JSON payload from stdin (or PAYLOAD env var), dispatches to the
requested function, and writes JSON result to stdout.

Usage:
    python3 scripts/analytics_bridge.py --function calculate_indicators

Functions:
    calculate_indicators  — Technical indicators from OHLCV data
    generate_signals      — ML-based trading signals
    generate_forecast     — Price forecasts with confidence intervals
    analyze_sentiment     — NLP sentiment analysis
    run_backtest          — Strategy backtesting with metrics
"""

import argparse
import json
import os
import sys
from typing import Any, Dict, Optional


def calculate_indicators(input_data: Dict[str, Any]) -> Dict[str, Any]:
    """Calculate basic technical indicators from OHLCV data."""
    ohlcv = input_data.get("ohlcv", [])
    params = input_data.get("params", {})

    if not ohlcv:
        return {"indicators": {}, "error": "No OHLCV data provided"}

    closes = [float(row.get("close", 0)) for row in ohlcv]
    highs = [float(row.get("high", 0)) for row in ohlcv]
    lows = [float(row.get("low", 0)) for row in ohlcv]
    volumes = [float(row.get("volume", 0)) for row in ohlcv]

    period = int(params.get("period", 20))

    # Simple Moving Average
    sma = []
    for i in range(len(closes)):
        if i < period - 1:
            sma.append(None)
        else:
            sma.append(sum(closes[i - period + 1 : i + 1]) / period)

    # RSI (Relative Strength Index)
    rsi = []
    for i in range(len(closes)):
        if i < period:
            rsi.append(None)
        else:
            gains = []
            losses = []
            for j in range(i - period + 1, i + 1):
                change = closes[j] - closes[j - 1]
                if change > 0:
                    gains.append(change)
                else:
                    losses.append(abs(change))
            avg_gain = sum(gains) / period if gains else 0
            avg_loss = sum(losses) / period if losses else 0.0001
            rs = avg_gain / avg_loss
            rsi.append(100 - (100 / (1 + rs)))

    # Bollinger Bands
    bollinger = []
    for i in range(len(closes)):
        if i < period - 1:
            bollinger.append(None)
        else:
            slice_data = closes[i - period + 1 : i + 1]
            mean = sum(slice_data) / period
            variance = sum((x - mean) ** 2 for x in slice_data) / period
            std = variance ** 0.5
            bollinger.append({
                "upper": mean + 2 * std,
                "middle": mean,
                "lower": mean - 2 * std,
            })

    # MACD
    ema_fast_period = int(params.get("ema_fast", 12))
    ema_slow_period = int(params.get("ema_slow", 26))
    signal_period = int(params.get("signal_period", 9))

    def ema(data, period):
        result = []
        multiplier = 2 / (period + 1)
        for i in range(len(data)):
            if i == 0:
                result.append(data[0])
            else:
                result.append(data[i] * multiplier + result[-1] * (1 - multiplier))
        return result

    ema_fast = ema(closes, ema_fast_period)
    ema_slow = ema(closes, ema_slow_period)
    macd_line = [f - s for f, s in zip(ema_fast, ema_slow)]
    signal_line = ema(macd_line, signal_period)
    macd_histogram = [m - s for m, s in zip(macd_line, signal_line)]

    return {
        "indicators": {
            "sma": sma[-1] if sma and sma[-1] is not None else None,
            "rsi": rsi[-1] if rsi and rsi[-1] is not None else None,
            "bollinger": bollinger[-1] if bollinger and bollinger[-1] is not None else None,
            "macd": {
                "line": macd_line[-1] if macd_line else None,
                "signal": signal_line[-1] if signal_line else None,
                "histogram": macd_histogram[-1] if macd_histogram else None,
            },
        },
        "last_close": closes[-1] if closes else None,
        "data_points": len(closes),
    }


def generate_signals(input_data: Dict[str, Any]) -> Dict[str, Any]:
    """Generate trading signals based on technical indicators."""
    indicators = input_data.get("indicators", {})
    rsi = indicators.get("rsi")
    macd = indicators.get("macd", {})
    bollinger = indicators.get("bollinger", {})

    signals = []

    if rsi is not None:
        if rsi < 30:
            signals.append({"type": "OVERSOLD", "direction": "BULLISH", "strength": "STRONG", "indicator": "RSI", "value": rsi})
        elif rsi > 70:
            signals.append({"type": "OVERBOUGHT", "direction": "BEARISH", "strength": "STRONG", "indicator": "RSI", "value": rsi})

    macd_hist = macd.get("histogram")
    if macd_hist is not None:
        if macd_hist > 0:
            signals.append({"type": "MACD_BULLISH", "direction": "BULLISH", "strength": "MODERATE", "indicator": "MACD", "value": macd_hist})
        else:
            signals.append({"type": "MACD_BEARISH", "direction": "BEARISH", "strength": "MODERATE", "indicator": "MACD", "value": macd_hist})

    bb_upper = bollinger.get("upper") if bollinger else None
    bb_lower = bollinger.get("lower") if bollinger else None
    last_close = input_data.get("last_close")

    if bb_upper and bb_lower and last_close:
        if last_close <= bb_lower:
            signals.append({"type": "BB_LOWER_BREAK", "direction": "BULLISH", "strength": "MODERATE", "indicator": "BOLLINGER"})
        elif last_close >= bb_upper:
            signals.append({"type": "BB_UPPER_BREAK", "direction": "BEARISH", "strength": "MODERATE", "indicator": "BOLLINGER"})

    direction = "NEUTRAL"
    if all(s["direction"] == "BULLISH" for s in signals) and signals:
        direction = "BULLISH"
    elif all(s["direction"] == "BEARISH" for s in signals) and signals:
        direction = "BEARISH"

    return {
        "signals": signals,
        "direction": direction,
        "signal_count": len(signals),
    }


def generate_forecast(input_data: Dict[str, Any]) -> Dict[str, Any]:
    """Generate simple price forecast with confidence interval."""
    ohlcv = input_data.get("ohlcv", [])
    horizon = int(input_data.get("horizon", 5))

    if not ohlcv:
        return {"forecast": None, "error": "No data provided"}

    closes = [float(row.get("close", 0)) for row in ohlcv]

    # Simple linear regression forecast
    n = len(closes)
    if n < 2:
        return {"forecast": None, "error": "Insufficient data"}

    x = list(range(n))
    x_mean = sum(x) / n
    y_mean = sum(closes) / n

    numerator = sum((x[i] - x_mean) * (closes[i] - y_mean) for i in range(n))
    denominator = sum((x[i] - x_mean) ** 2 for i in range(n))

    if denominator == 0:
        slope = 0
    else:
        slope = numerator / denominator

    intercept = y_mean - slope * x_mean

    # Forecast
    forecast_values = []
    for h in range(1, horizon + 1):
        predicted = intercept + slope * (n + h - 1)
        forecast_values.append(round(predicted, 4))

    # Confidence interval (simplified)
    residuals = [closes[i] - (intercept + slope * x[i]) for i in range(n)]
    mse = sum(r ** 2 for r in residuals) / n if n > 0 else 0
    std_error = mse ** 0.5

    confidence_interval = {
        "low": [round(v - 1.96 * std_error, 4) for v in forecast_values],
        "high": [round(v + 1.96 * std_error, 4) for v in forecast_values],
    }

    return {
        "forecast": forecast_values,
        "confidence_interval": confidence_interval,
        "confidence_score": max(0, min(1, 1 - (std_error / y_mean if y_mean else 1))),
        "model": "linear_regression",
        "horizon": horizon,
    }


def analyze_sentiment(input_data: Dict[str, Any]) -> Dict[str, Any]:
    """Simple rule-based sentiment analysis."""
    text = input_data.get("text", "")

    if not text:
        return {"sentiment": "NEUTRAL", "score": 0.0, "error": "No text provided"}

    positive_words = [
        "surge", "rally", "gain", "profit", "growth", "bullish", "upgrade",
        "beat", "exceed", "strong", "positive", "optimistic", "rise", "higher",
        "naik", "untung", "positif", "tumbuh", "optimis", "bullish",
    ]
    negative_words = [
        "plunge", "drop", "loss", "decline", "bearish", "downgrade", "miss",
        "weak", "negative", "pessimistic", "fall", "lower", "sell",
        "turun", "rugi", "negatif", "lemah", "pesimis", "bearish", "jual",
    ]

    text_lower = text.lower()
    positive_count = sum(1 for w in positive_words if w in text_lower)
    negative_count = sum(1 for w in negative_words if w in text_lower)

    total = positive_count + negative_count
    if total == 0:
        sentiment = "NEUTRAL"
        score = 0.0
    elif positive_count > negative_count:
        sentiment = "POSITIVE"
        score = positive_count / total
    elif negative_count > positive_count:
        sentiment = "NEGATIVE"
        score = -(negative_count / total)
    else:
        sentiment = "NEUTRAL"
        score = 0.0

    return {
        "sentiment": sentiment,
        "score": round(score, 4),
        "positive_signals": positive_count,
        "negative_signals": negative_count,
        "model": "rule_based_v1",
    }


def run_backtest(input_data: Dict[str, Any]) -> Dict[str, Any]:
    """Run a simple backtest on historical data."""
    ohlcv = input_data.get("ohlcv", [])
    strategy = input_data.get("strategy", "sma_crossover")
    initial_capital = float(input_data.get("initial_capital", 100000))
    params = input_data.get("params", {})

    if not ohlcv:
        return {"error": "No OHLCV data provided"}

    closes = [float(row.get("close", 0)) for row in ohlcv]
    period = int(params.get("period", 20))

    # Simple SMA crossover strategy
    position = 0
    capital = initial_capital
    trades = []
    equity_curve = []

    for i in range(len(closes)):
        if i < period:
            equity_curve.append(capital)
            continue

        sma = sum(closes[i - period + 1 : i + 1]) / period

        if strategy == "sma_crossover":
            if closes[i] > sma and position == 0:
                # Buy
                shares = int(capital / closes[i])
                if shares > 0:
                    capital -= shares * closes[i]
                    position = shares
                    trades.append({"type": "BUY", "price": closes[i], "shares": shares, "date": ohlcv[i].get("trade_date", "")})
            elif closes[i] < sma and position > 0:
                # Sell
                capital += position * closes[i]
                trades.append({"type": "SELL", "price": closes[i], "shares": position, "date": ohlcv[i].get("trade_date", "")})
                position = 0

        equity = capital + position * closes[i]
        equity_curve.append(round(equity, 2))

    final_capital = capital + position * closes[-1] if closes else initial_capital
    total_return = ((final_capital - initial_capital) / initial_capital) * 100

    # Calculate metrics
    returns = []
    for i in range(1, len(equity_curve)):
        if equity_curve[i - 1] > 0:
            returns.append((equity_curve[i] - equity_curve[i - 1]) / equity_curve[i - 1])

    # Max drawdown
    peak = equity_curve[0] if equity_curve else initial_capital
    max_dd = 0
    for eq in equity_curve:
        if eq > peak:
            peak = eq
        dd = (peak - eq) / peak if peak > 0 else 0
        if dd > max_dd:
            max_dd = dd

    # Sharpe ratio (simplified, assuming 0% risk-free rate)
    if returns:
        avg_return = sum(returns) / len(returns)
        std_return = (sum((r - avg_return) ** 2 for r in returns) / len(returns)) ** 0.5
        sharpe = (avg_return / std_return * (252 ** 0.5)) if std_return > 0 else 0
    else:
        sharpe = 0

    # Win rate
    winning_trades = 0
    total_round_trips = 0
    for i in range(0, len(trades) - 1, 2):
        if i + 1 < len(trades):
            total_round_trips += 1
            if trades[i + 1]["price"] > trades[i]["price"]:
                winning_trades += 1

    win_rate = (winning_trades / total_round_trips * 100) if total_round_trips > 0 else 0

    return {
        "initial_capital": initial_capital,
        "final_capital": round(final_capital, 2),
        "total_return_pct": round(total_return, 2),
        "max_drawdown_pct": round(max_dd * 100, 2),
        "sharpe_ratio": round(sharpe, 4),
        "win_rate_pct": round(win_rate, 2),
        "total_trades": len(trades),
        "total_round_trips": total_round_trips,
        "equity_curve": equity_curve[-30:],
        "strategy": strategy,
    }


FUNCTIONS = {
    "calculate_indicators": calculate_indicators,
    "generate_signals": generate_signals,
    "generate_forecast": generate_forecast,
    "analyze_sentiment": analyze_sentiment,
    "run_backtest": run_backtest,
}


def main():
    parser = argparse.ArgumentParser(description="Analytics bridge for PHP <-> Python")
    parser.add_argument("--function", required=True, help="Function to call")
    args = parser.parse_args()

    # Read payload from env or stdin
    payload = os.environ.get("PAYLOAD")
    if not payload:
        payload = sys.stdin.read()

    try:
        data = json.loads(payload)
        input_data = data.get("input", data)
    except json.JSONDecodeError as e:
        print(json.dumps({"error": f"Invalid JSON: {str(e)}"}))
        sys.exit(1)

    func = FUNCTIONS.get(args.function)
    if func is None:
        print(json.dumps({"error": f"Unknown function: {args.function}"}))
        sys.exit(1)

    try:
        result = func(input_data)
        print(json.dumps(result, default=str))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
