import os

def update_scss_files():
    base_path = '/home/deploy/sites'
    target_suffix = 'utheme/src/style.scss'
    
    # Список для хранения путей измененных файлов
    modified_files = []
    
    new_content = """
.tc-brand-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
"""

    if not os.path.exists(base_path):
        print(f"Ошибка: Директория {base_path} не найдена.")
        return

    for site_folder in os.listdir(base_path):
        full_folder_path = os.path.join(base_path, site_folder)
        
        if os.path.isdir(full_folder_path):
            file_path = os.path.join(full_folder_path, target_suffix)
            
            if os.path.exists(file_path):
                try:
                    with open(file_path, 'r', encoding='utf-8') as f:
                        content = f.read()
                    
                    if '.tc-brand-list' not in content:
                        with open(file_path, 'a', encoding='utf-8') as f:
                            f.write(f"\n{new_content}")
                        
                        # Добавляем файл в наш список
                        modified_files.append(file_path)
                        print(f"✅ Обработан: {site_folder}")
                    else:
                        print(f"ℹ️ Пропущен (уже есть): {site_folder}")
                        
                except Exception as e:
                    print(f"❌ Ошибка в {site_folder}: {e}")

    # Финальный отчет
    print("\n" + "="*50)
    if modified_files:
        print(f"РАБОТА ЗАВЕРШЕНА. Изменено файлов: {len(modified_files)}")
        print("Список модифицированных файлов:")
        for path in modified_files:
            print(f" - {path}")
    else:
        print("Изменений не внесено. Подходящие файлы не найдены или уже содержат нужный код.")
    print("="*50)

if __name__ == "__main__":
    update_scss_files()