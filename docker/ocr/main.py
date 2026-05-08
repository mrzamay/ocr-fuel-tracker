import io
import re
from dataclasses import dataclass
from typing import Any

import pytesseract
from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.responses import JSONResponse
from PIL import Image, ImageEnhance, ImageFilter, ImageOps

try:
    from pillow_heif import register_heif_opener

    register_heif_opener()
except Exception:
    pass

app = FastAPI(title="Receipt OCR Microservice")


@dataclass
class Candidate:
    value: float
    score: float
    source: str


def normalize_number(value: str) -> float | None:
    cleaned = value.replace(" ", "").replace("\u00a0", "").replace(",", ".")
    cleaned = re.sub(r"[^0-9.]", "", cleaned)
    if cleaned.count(".") > 1:
        parts = cleaned.split(".")
        cleaned = "".join(parts[:-1]) + "." + parts[-1]

    try:
        return round(float(cleaned), 2)
    except ValueError:
        return None


def normalize_text(text: str) -> str:
    replacements = {
        "₽": " руб ",
        "р.": " руб ",
        "p.": " руб ",
        "oбьем": "объем",
        "обьем": "объем",
        "итoг": "итог",
        "иtог": "итог",
    }
    result = text.lower().replace("\r", "\n")
    for old, new in replacements.items():
        result = result.replace(old, new)
    result = re.sub(r"[ \t]+", " ", result)
    return result


def numbers_in_line(line: str) -> list[float]:
    numbers = []
    for match in re.finditer(r"\d[\d\s]{0,6}[,.]\d{1,3}|\d{1,6}", line):
        value = normalize_number(match.group(0))
        if value is not None:
            numbers.append(value)
    return numbers


def is_fuel_grade(value: float) -> bool:
    return value in {92, 95, 98, 100}


def add_candidate(candidates: list[Candidate], value: float | None, score: float, source: str) -> None:
    if value is None:
        return
    candidates.append(Candidate(value=value, score=score, source=source))


def best_candidate(candidates: list[Candidate]) -> Candidate | None:
    if not candidates:
        return None
    return sorted(candidates, key=lambda item: (item.score, item.value), reverse=True)[0]


def extract_data_from_text(text: str) -> dict[str, Any]:
    normalized = normalize_text(text)
    lines = [line.strip() for line in normalized.split("\n") if line.strip()]
    compact = " ".join(lines)

    amount_candidates: list[Candidate] = []
    volume_candidates: list[Candidate] = []
    price_candidates: list[Candidate] = []

    amount_keywords = r"итог|итого|сумма|к оплате|оплата|всего|total|amount"
    volume_keywords = r"литр|литров|л\b|кол-во|количество|объем|доза|volume|qty"
    price_keywords = r"цена|стоимость\s+л|price"
    fuel_keywords = r"аи-?\s?\d{2,3}|aи-?\s?\d{2,3}|ai-?\s?\d{2,3}|дт|diesel|бензин|fuel"

    for line in lines:
        values = numbers_in_line(line)
        if not values:
            continue

        has_amount_keyword = re.search(amount_keywords, line)
        has_volume_keyword = re.search(volume_keywords, line)
        has_price_keyword = re.search(price_keywords, line)
        has_fuel_keyword = re.search(fuel_keywords, line)

        if has_amount_keyword:
            for value in values:
                if 50 <= value <= 500000:
                    add_candidate(amount_candidates, value, 95, f"amount keyword: {line}")

        if has_volume_keyword:
            keyword_match = re.search(r"(?:литр|литров|л\b|кол-во|количество|объем|доза|volume|qty)[^0-9]{0,20}(\d{1,3}[,.]\d{1,3})", line)
            if keyword_match:
                add_candidate(volume_candidates, normalize_number(keyword_match.group(1)), 94, f"volume keyword: {line}")
            else:
                for value in values:
                    if 1 <= value <= 300 and not is_fuel_grade(value):
                        add_candidate(volume_candidates, value, 72, f"volume keyword: {line}")

        if has_price_keyword:
            price_match = re.search(r"(?:цена|стоимость\s+л|price)[^0-9]{0,20}(\d{1,3}[,.]\d{1,3})", line)
            if price_match:
                add_candidate(price_candidates, normalize_number(price_match.group(1)), 82, f"price keyword: {line}")
            else:
                for value in values:
                    if 10 <= value <= 300 and not is_fuel_grade(value):
                        add_candidate(price_candidates, value, 70, f"price keyword: {line}")

        if has_fuel_keyword and len(values) >= 2:
            plausible_volumes = [value for value in values if 1 <= value <= 300]
            plausible_amounts = [value for value in values if 50 <= value <= 500000]
            plausible_prices = [value for value in values if 10 <= value <= 300 and not is_fuel_grade(value)]

            plausible_volumes = [
                value for value in plausible_volumes
                if not is_fuel_grade(value) and value not in plausible_prices[:1]
            ]

            if plausible_volumes:
                add_candidate(volume_candidates, plausible_volumes[0], 78, f"fuel row: {line}")
            if plausible_amounts:
                add_candidate(amount_candidates, max(plausible_amounts), 78, f"fuel row: {line}")
            if plausible_prices:
                # Prefer values that look like price per liter, not total amount.
                price = min(plausible_prices, key=lambda item: abs(item - 60))
                add_candidate(price_candidates, price, 55, f"fuel row price: {line}")

    for match in re.finditer(rf"(?:{amount_keywords})[^0-9]{{0,20}}(\d[\d\s]{{0,6}}[,.]\d{{2}})", compact):
        add_candidate(amount_candidates, normalize_number(match.group(1)), 88, "amount compact")

    for match in re.finditer(r"(\d{2,6}[,.]\d{2})\s*(?:руб|rub)", compact):
        add_candidate(amount_candidates, normalize_number(match.group(1)), 65, "rub suffix")

    for match in re.finditer(rf"(?:{volume_keywords})[^0-9]{{0,20}}(\d{{1,3}}[,.]\d{{2,3}})", compact):
        add_candidate(volume_candidates, normalize_number(match.group(1)), 85, "volume compact")

    for match in re.finditer(r"(\d{1,3}[,.]\d{2,3})\s*(?:л|литр|l\b)", compact):
        add_candidate(volume_candidates, normalize_number(match.group(1)), 80, "liter suffix")

    amount = best_candidate(amount_candidates)
    volume = best_candidate(volume_candidates)
    unit_price = best_candidate(price_candidates)
    fuel_type = extract_fuel_type(normalized)
    station_name = extract_station_name(normalized)

    # If total and unit price are known, recover liters.
    if not volume and amount and unit_price and unit_price.value > 0:
        calculated = round(amount.value / unit_price.value, 2)
        if 1 <= calculated <= 300:
            volume = Candidate(calculated, 62, "calculated amount / unit_price")

    # If liters and total are known, recover unit price.
    if not unit_price and amount and volume and volume.value > 0:
        calculated = round(amount.value / volume.value, 2)
        if 10 <= calculated <= 300:
            unit_price = Candidate(calculated, 45, "calculated amount / volume")

    confidence = 0
    if amount:
        confidence += 45
    if volume:
        confidence += 45
    if unit_price:
        confidence += 10

    return {
        "amount": amount.value if amount else None,
        "volume": volume.value if volume else None,
        "unit_price": unit_price.value if unit_price else None,
        "fuel_type": fuel_type,
        "station_name": station_name,
        "confidence": min(confidence, 100),
        "sources": {
            "amount": amount.source if amount else None,
            "volume": volume.source if volume else None,
            "unit_price": unit_price.source if unit_price else None,
        },
    }


def extract_fuel_type(text: str) -> str | None:
    patterns = [
        (r"(?:аи|aи|ai)[\s-]?(92|95|98|100)", "АИ-{}"),
        (r"\bдт\b|diesel|дизель", "ДТ"),
        (r"пропан|метан|газ", "Газ"),
    ]

    for pattern, template in patterns:
        match = re.search(pattern, text)
        if match:
            if "{}" in template:
                return template.format(match.group(1))
            return template

    return None


def extract_station_name(text: str) -> str | None:
    known_stations = [
        "лукойл",
        "lukoil",
        "газпромнефть",
        "газпром",
        "gpn",
        "роснефть",
        "татнефть",
        "shell",
        "bp",
        "башнефть",
        "трасса",
        "нефтьмагистраль",
    ]

    aliases = {
        "lukoil": "Лукойл",
        "gpn": "Газпромнефть",
        "shell": "Shell",
        "bp": "BP",
    }

    for station in known_stations:
        if station in text:
            return aliases.get(station, station.capitalize())

    return None


def prepare_image(image: Image.Image) -> Image.Image:
    image = ImageOps.exif_transpose(image)
    image = image.convert("RGB")

    max_side = 2200
    width, height = image.size
    scale = min(max_side / max(width, height), 1)
    if scale < 1:
        image = image.resize((int(width * scale), int(height * scale)))

    return image


def preprocess_variants(image: Image.Image) -> list[tuple[str, Image.Image]]:
    gray = image.convert("L")
    autocontrast = ImageOps.autocontrast(gray)
    sharp = autocontrast.filter(ImageFilter.SHARPEN)
    high_contrast = ImageEnhance.Contrast(sharp).enhance(1.8)
    threshold = high_contrast.point(lambda pixel: 255 if pixel > 165 else 0)

    return [
        ("gray", gray),
        ("autocontrast", autocontrast),
        ("high_contrast", high_contrast),
        ("threshold", threshold),
    ]


def run_ocr(image: Image.Image) -> dict[str, Any]:
    attempts = []
    configs = [
        ("psm6", r"--oem 3 --psm 6"),
        ("psm4", r"--oem 3 --psm 4"),
        ("psm11", r"--oem 3 --psm 11"),
    ]

    for variant_name, variant in preprocess_variants(image):
        for config_name, config in configs:
            text = pytesseract.image_to_string(variant, lang="rus+eng", config=config)
            extracted = extract_data_from_text(text)
            score = extracted["confidence"] + min(len(text.strip()) / 100, 20)
            attempts.append({
                "variant": variant_name,
                "config": config_name,
                "text": text,
                "extracted": extracted,
                "score": score,
            })

    return max(attempts, key=lambda item: item["score"])


@app.post("/recognize")
async def recognize_receipt(file: UploadFile = File(...)):
    if file.content_type and not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Invalid file type.")

    try:
        contents = await file.read()
        image = Image.open(io.BytesIO(contents))
        prepared = prepare_image(image)
        best = run_ocr(prepared)

        return JSONResponse(content={
            "success": True,
            "extracted": best["extracted"],
            "raw_text": best["text"],
            "ocr": {
                "variant": best["variant"],
                "config": best["config"],
                "score": best["score"],
            },
        })

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
