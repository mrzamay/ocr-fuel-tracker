import io
import re
import pytesseract
from PIL import Image, ImageEnhance, ImageFilter
from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.responses import JSONResponse

app = FastAPI(title="Receipt OCR Microservice")

def preprocess_image(image: Image.Image) -> Image.Image:
    """Предварительная обработка изображения для улучшения качества OCR"""
    # Перевод в черно-белый формат
    image = image.convert('L')
    # Увеличение контраста
    enhancer = ImageEnhance.Contrast(image)
    image = enhancer.enhance(2.0)
    # Применение фильтра резкости
    image = image.filter(ImageFilter.SHARPEN)
    return image

def extract_data_from_text(text: str) -> dict:
    """Извлечение суммы и объема литров из текста с помощью регулярных выражений"""
    result = {"amount": None, "volume": None}
    
    # Регулярные выражения
    # Ищем сумму (Итог, Сумма, Оплата) - например "ИТОГО 1250.50" или "= 1250,50"
    amount_patterns = [
        r'(?i)(?:итог|сумма|итого|к оплате)[\s\.:]*=?\s*(\d+[\.,]\d{2})',
        r'(?i)total[\s\.:]*=?\s*(\d+[\.,]\d{2})'
    ]
    
    # Ищем объем в литрах - например "40.50 л" или "Доза: 40,50"
    volume_patterns = [
        r'(\d+[\.,]\d{2,3})\s*(?:л|литров|l|liters)',
        r'(?i)(?:доза|объем|количество|кол-во)[\s\.:]*=?\s*(\d+[\.,]\d{2,3})'
    ]

    # Поиск суммы
    for pattern in amount_patterns:
        match = re.search(pattern, text)
        if match:
            # Заменяем запятую на точку для преобразования во float
            amount_str = match.group(1).replace(',', '.')
            try:
                result["amount"] = float(amount_str)
                break
            except ValueError:
                continue

    # Поиск литров
    for pattern in volume_patterns:
        match = re.search(pattern, text)
        if match:
            volume_str = match.group(1).replace(',', '.')
            try:
                result["volume"] = float(volume_str)
                break
            except ValueError:
                continue
                
    return result

@app.post("/recognize")
async def recognize_receipt(file: UploadFile = File(...)):
    # Проверка типа файла
    if not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Invalid file type. Please upload an image.")

    try:
        # Чтение изображения в память
        contents = await file.read()
        image = Image.open(io.BytesIO(contents))
        
        # Предобработка
        processed_image = preprocess_image(image)
        
        # Распознавание (русский + английский)
        custom_config = r'--oem 3 --psm 4'
        text = pytesseract.image_to_string(processed_image, lang='rus+eng', config=custom_config)
        
        # Парсинг данных
        extracted_data = extract_data_from_text(text)
        
        return JSONResponse(content={
            "success": True,
            "extracted": extracted_data,
            "raw_text": text  # Возвращаем также сырой текст для отладки
        })
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Error processing image: {str(e)}")
