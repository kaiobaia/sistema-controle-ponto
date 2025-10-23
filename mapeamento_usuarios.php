<?php
/**
 * Arquivo de configuração para mapeamento de usuários
 * 
 * Este arquivo define quais usuários podem fazer login com um email
 * mas visualizar os dados de outro usuário.
 * 
 * Formato do array:
 * 'email_que_faz_login' => 'email_do_usuario_que_sera_exibido'
 */

$mapeamento_usuarios = [
    // Exemplo: kaio.baia@kamilla.oliveira faz login mas vê dados de kamilla.oliveira
    'kaio.baia@kamilla.oliveira' => 'kamilla.oliveira',
    
    // Adicione mais mapeamentos conforme necessário
    // 'usuario.login@exemplo.com' => 'usuario.dados@exemplo.com',
    // 'joao.silva@maria.santos' => 'maria.santos',
    // 'admin.teste@usuario.real' => 'usuario.real',
];

/**
 * Função para obter o usuário de exibição baseado no usuário de login
 * 
 * @param string $usuario_login O email do usuário que fez login
 * @return string O email do usuário cujos dados devem ser exibidos
 */
function obter_usuario_exibicao($usuario_login) {
    global $mapeamento_usuarios;
    
    // Remove o domínio se presente
    $usuario_sem_dominio = strstr($usuario_login, '@', true);
    
    // Verifica se existe mapeamento
    if (isset($mapeamento_usuarios[$usuario_sem_dominio])) {
        return $mapeamento_usuarios[$usuario_sem_dominio] . '@gf.local';
    }
    
    // Se não há mapeamento, retorna o usuário original
    return $usuario_login;
}

/**
 * Função para verificar se um usuário tem mapeamento
 * 
 * @param string $usuario_login O email do usuário que fez login
 * @return bool True se existe mapeamento, False caso contrário
 */
function tem_mapeamento($usuario_login) {
    global $mapeamento_usuarios;
    
    $usuario_sem_dominio = strstr($usuario_login, '@', true);
    return isset($mapeamento_usuarios[$usuario_sem_dominio]);
}

/**
 * Função para obter o usuário de login original (sem domínio)
 * 
 * @param string $usuario_login O email do usuário que fez login
 * @return string O nome do usuário sem domínio
 */
function obter_usuario_login_original($usuario_login) {
    return strstr($usuario_login, '@', true);
}
?> 