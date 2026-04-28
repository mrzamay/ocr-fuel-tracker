from fastapi import FastAPI, UploadFile, File

app = FastAPI(title="Receipt OCR Microservice")

@app.get("/")
def read_root():
    return {"status": "OCR service is running"}

@app.post("/recognize")
async def recognize_receipt(file: UploadFile = File(...)):
    # Логика распознавания (Tesseract) будет реализована на Шаге 4
    return {"amount": 0.0, "volume": 0.0}