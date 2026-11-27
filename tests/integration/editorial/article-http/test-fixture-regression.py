from importlib import import_module


if __name__ == "__main__":
    raise SystemExit(
        import_module("regression-tests").main()
    )
