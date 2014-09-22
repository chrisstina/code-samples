package com.herocraft.logcollector.producer;

import javax.jms.JMSException;
import javax.jms.MapMessage;
import javax.jms.Message;
import javax.jms.Session;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.jms.core.JmsTemplate;
import org.springframework.jms.core.MessageCreator;

public class LogSender {
		   
    @Autowired
    private JmsTemplate jmsTemplate;
	   
    public void newLogFileLine(final String message){
    	try {
    		jmsTemplate.send(new MessageCreator() {
    			public Message createMessage(Session session) throws JMSException {
    				MapMessage mapMessage = session.createMapMessage();
    				mapMessage.setString("logLine", message);
    				return mapMessage;
    			}
    		});
    		System.out.println("Log sent" + message);
   		} catch (Exception e) {
   			System.out.println("Error sending log" + e.toString() + message);
   		}
    }
}