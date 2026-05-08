import io
import re
import pytesseract
from PIL import Image
from fastapi import FastAPI, UploadFile, File, HTTPException
from fastapi.responses import JSONResponse

app = FastAPI(title="Receipt OCR Microservice")

def extract_data_from_text(text: str) -> dict:
    """Извлечение суммы и объема литров из текста с помощью мощных regex"""
    result = {"amount": None, "volume": None}
    
    # Очищаем текст от мусора и переводим в нижний регистр
    text_clean = text.lower().replace('\n', ' ')
    
    # Ищем сумму (Итог, Сумма, Оплата) - например "ИТОГО = 1250.50", "= 1250,50", "сумма 1500"
    amount_patterns = [
        r'(?:итог|итого|сумма|к оплате|total)[\s\.\:\-]*=?[\s]*(\d{2,5}[\.,]\d{2})',
        r'=(\d{2,5}[\.,]\d{2})',
        r'(\d{2,5}[\.,]\d{2})\s*(?:руб|rub|р\.)'
    ]
    
    # Ищем объем в литрах - например "40.50 л", "доза 30.00", "объем: 20"
    volume_patterns = [
        r'(\d{1,3}[\.,]\d{2,3})\s*(?:л|литров|l|lit|литр)',
        r'(?:доза|объем|кол-во|количество)[\s\.\:\-]*=?[\s]*(\d{1,3}[\.,]\d{2,3})',
    ]

    # Поиск суммы
    for pattern in amount_patterns:
        match = re.search(pattern, text_clean)
        if match:
            amount_str = match.group(1).replace(',', '.')
            try:
                result["amount"] = float(amount_str)
                break
            except ValueError:
                continue

    # Поиск литров
    for pattern in volume_patterns:
        match = re.search(pattern, text_clean)
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
    if not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Invalid file type.")

    try:
        contents = await file.read()
        image = Image.open(io.BytesIO(contents))
        
        # Переводим в черно-белый для лучшего распознавания
        image = image.convert('L')
        
        # Улучшенный конфиг для Tesseract (PSM 3 - автоматическое определение блоков)
        custom_config = r'--oem 3 --psm 3'
        text = pytesseract.image_to_string(image, lang='rus+eng', config=custom_config)
        
        extracted_data = extract_data_from_text(text)
        
        # Даже если ничего не найдем, возвращаем сырой текст, чтобы бэкенд не падал
        return JSONResponse(content={
            "success": True,
            "extracted": extracted_data,
            "raw_text": text 
        })
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
